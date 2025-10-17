<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use OnixSystemsPHP\HyperfScorm\Job\ProcessScormPackageJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for managing asynchronous SCORM package processing
 */
#[Service]
class AsyncScormProcessingService
{
    public function __construct(
        private readonly DriverFactory $queueDriverFactory,
        private readonly ScormJobIdGenerator $jobIdGenerator,
        private readonly ScormJobStatusService $jobStatusService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function queueProcessing(
        UploadedFile $uploadedFile,
        int $userId,
        array $metadata = []
    ): string {
        $jobId = $this->jobIdGenerator->generate();

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


    public function getProcessingProgress(string $jobId): ?array
    {
        return $this->jobStatusService->getProgress($jobId);
    }

    public function getProcessingResult(string $jobId): ?array
    {
        return $this->jobStatusService->getResult($jobId);
    }

    public function cancelProcessing(string $jobId): bool
    {
        $progress = $this->getProcessingProgress($jobId);
        if ($progress && $progress['status'] === 'processing') {
            return false;
        }

        $this->jobStatusService->updateProgress($jobId, [
            'status' => 'cancelled',
            'progress' => 0,
            'stage' => 'cancelled',
            'cancelled_at' => time(),
        ]);

        $this->jobStatusService->setResult($jobId, [
            'status' => 'cancelled',
            'cancelled_at' => time(),
        ]);

        $this->logger->info('SCORM processing job cancelled', ['job_id' => $jobId]);

        return true;
    }
}
