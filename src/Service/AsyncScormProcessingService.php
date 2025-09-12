<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\Coroutine\Parallel;
use Hyperf\Redis\Redis;
use OnixSystemsPHP\HyperfScorm\Job\ProcessScormPackageJob;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for managing asynchronous SCORM package processing
 * Utilizes Hyperf's coroutine capabilities for efficient processing
 */
class AsyncScormProcessingService
{
    private const MAX_PARALLEL_JOBS = 3;

    public function __construct(
        private readonly DriverFactory $queueDriverFactory,
        private readonly Redis $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Queue SCORM package for asynchronous processing
     */
    public function queueProcessing(
        UploadedFile $uploadedFile,
        int $userId,
        array $metadata = []
    ): string {
        $jobId = $this->generateJobId();

        try {
            $tempFilePath = $uploadedFile->getPathname();

            $job = new ProcessScormPackageJob(
                jobId: $jobId,
                tempFilePath: $tempFilePath,
                originalFilename: $uploadedFile->getClientFilename(),
                fileSize: $uploadedFile->getSize(),
                userId: $userId,
                metadata: $metadata
            );

            $queue = $this->queueDriverFactory->get(ProcessScormPackageJob::QUEUE_NAME);
            $queue->push($job);

            $this->logger->info('SCORM processing job queued', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'filename' => $uploadedFile->getClientFilename(),
                'file_size' => $uploadedFile->getSize(),
            ]);

            return $jobId;

        } catch (Throwable $e) {
            $this->logger->error('Failed to queue SCORM processing job', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new ScormParsingException(
                "Failed to queue SCORM processing: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Queue multiple SCORM packages for parallel processing
     */
    public function queueBatchProcessing(array $uploadedFiles, int $userId, array $metadata = []): array
    {
        if (count($uploadedFiles) > self::MAX_PARALLEL_JOBS) {
            throw new ScormParsingException("Too many files. Maximum " . self::MAX_PARALLEL_JOBS . " files allowed per batch.");
        }

        $parallel = new Parallel(count($uploadedFiles));
        $jobIds = [];

        foreach ($uploadedFiles as $index => $uploadedFile) {
            $parallel->add(function () use ($uploadedFile, $userId, $metadata, $index) {
                return $this->queueProcessing($uploadedFile, $userId, array_merge($metadata, ['batch_index' => $index]));
            });
        }

        try {
            $results = $parallel->wait(true);

            foreach ($results as $result) {
                if ($result instanceof Throwable) {
                    $this->logger->error('Batch processing job failed', [
                        'error' => $result->getMessage(),
                        'user_id' => $userId,
                    ]);
                    throw $result;
                }
                $jobIds[] = $result;
            }

            $this->logger->info('Batch SCORM processing jobs queued', [
                'job_ids' => $jobIds,
                'user_id' => $userId,
                'count' => count($jobIds),
            ]);

            return $jobIds;

        } catch (Throwable $e) {
            throw new ScormParsingException(
                "Failed to queue batch SCORM processing: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get processing progress for a job
     */
    public function getProcessingProgress(string $jobId): ?array
    {
        $progressKey = ProcessScormPackageJob::PROGRESS_KEY_PREFIX . $jobId;
        $progressData = $this->redis->get($progressKey);

        if (!$progressData) {
            return null;
        }

        return json_decode($progressData, true);
    }

    /**
     * Get processing result for a job
     */
    public function getProcessingResult(string $jobId): ?array
    {
        $resultKey = ProcessScormPackageJob::RESULT_KEY_PREFIX . $jobId;
        $resultData = $this->redis->get($resultKey);

        if (!$resultData) {
            return null;
        }

        return json_decode($resultData, true);
    }

    /**
     * Get batch processing status
     */
    public function getBatchProcessingStatus(array $jobIds): array
    {
        $parallel = new Parallel(count($jobIds));

        foreach ($jobIds as $jobId) {
            $parallel->add(function () use ($jobId) {
                return [
                    'job_id' => $jobId,
                    'progress' => $this->getProcessingProgress($jobId),
                    'result' => $this->getProcessingResult($jobId),
                ];
            });
        }

        $results = $parallel->wait(true);
        $status = [
            'total' => count($jobIds),
            'completed' => 0,
            'processing' => 0,
            'failed' => 0,
            'jobs' => [],
        ];

        foreach ($results as $result) {
            if ($result instanceof Throwable) {
                continue;
            }

            $status['jobs'][] = $result;

            if ($result['progress']) {
                switch ($result['progress']['status']) {
                    case 'completed':
                        $status['completed']++;
                        break;
                    case 'processing':
                        $status['processing']++;
                        break;
                    case 'failed':
                    case 'permanently_failed':
                        $status['failed']++;
                        break;
                }
            }
        }

        return $status;
    }

    /**
     * Cancel processing job (if still queued)
     */
    public function cancelProcessing(string $jobId): bool
    {
        $progressKey = ProcessScormPackageJob::PROGRESS_KEY_PREFIX . $jobId;
        $resultKey = ProcessScormPackageJob::RESULT_KEY_PREFIX . $jobId;

        // Check if job is still processing
        $progress = $this->getProcessingProgress($jobId);
        if ($progress && $progress['status'] === 'processing') {
            // Cannot cancel already processing job
            return false;
        }

        // Mark as cancelled
        $this->redis->setex($progressKey, 3600, json_encode([
            'status' => 'cancelled',
            'progress' => 0,
            'stage' => 'cancelled',
            'cancelled_at' => time(),
        ]));

        $this->redis->setex($resultKey, 86400, json_encode([
            'status' => 'cancelled',
            'cancelled_at' => time(),
        ]));

        $this->logger->info('SCORM processing job cancelled', ['job_id' => $jobId]);

        return true;
    }

    /**
     * Cleanup expired job data
     */
    public function cleanupExpiredJobs(): int
    {
        $progressPattern = ProcessScormPackageJob::PROGRESS_KEY_PREFIX . '*';
        $resultPattern = ProcessScormPackageJob::RESULT_KEY_PREFIX . '*';

        $progressKeys = $this->redis->keys($progressPattern);
        $resultKeys = $this->redis->keys($resultPattern);

        $cleanedCount = 0;
        $expiredBefore = time() - 86400; // 24 hours ago

        foreach (array_merge($progressKeys, $resultKeys) as $key) {
            $data = $this->redis->get($key);
            if (!$data) {
                continue;
            }

            $decoded = json_decode($data, true);
            $completedAt = $decoded['completed_at'] ?? $decoded['failed_at'] ?? $decoded['cancelled_at'] ?? null;

            if ($completedAt && $completedAt < $expiredBefore) {
                $this->redis->del($key);
                $cleanedCount++;
            }
        }

        if ($cleanedCount > 0) {
            $this->logger->info('Cleaned up expired SCORM processing jobs', [
                'cleaned_count' => $cleanedCount,
            ]);
        }

        return $cleanedCount;
    }

    /**
     * Generate unique job ID
     */
    private function generateJobId(): string
    {
        return 'scorm_' . uniqid() . '_' . time();
    }

}
