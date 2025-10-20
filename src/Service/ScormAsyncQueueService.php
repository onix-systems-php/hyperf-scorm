<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormAsyncJobDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Job\ProcessScormPackageJob;
use OnixSystemsPHP\HyperfScorm\Service\AsyncScormProcessingService;

/**
 * Service for managing async SCORM processing queue
 * Handles job creation, status tracking, and queue management
 */
#[Service]
class ScormAsyncQueueService
{
    public function __construct(
        private readonly DriverFactory $driverFactory,
        private readonly ScormJobIdGenerator $jobIdGenerator,
        private readonly ScormTempFileService $tempFileService,
        private readonly ScormJobStatusService $jobStatusService,
        private readonly AsyncScormProcessingService $processingService,
    ) {}

    /**
     * Queue SCORM package for async processing
     *
     * @param ScormUploadDTO $dto Upload data transfer object
     * @return ScormAsyncJobDTO Job status information
     */
    public function queueProcessing(ScormUploadDTO $dto): ScormAsyncJobDTO
    {
        // Generate unique job ID
        $jobId = $this->jobIdGenerator->generate();

        // Save uploaded file to temporary location
        $tempPath = $this->tempFileService->saveTempFile($dto->file);

        // Initialize job status in Redis
        $this->jobStatusService->initializeJob($jobId, [
            'job_id' => $jobId,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'queued',
            'file_name' => $dto->file->getClientFilename(),
            'file_size' => $dto->file->getSize(),
            'created_at' => time(),
        ]);

        // Push job to async queue
        $driver = $this->driverFactory->get('scorm-processing');
        $driver->push(new ProcessScormPackageJob(
            $jobId,
            $tempPath,
            $dto->file->getClientFilename(),
            $dto->file->getSize(),
            1, // TODO: Get actual user ID from context
            [
                'title' => $dto->title,
                'description' => $dto->description ?? null,
            ]
        ));

        // Return job status DTO
        return ScormAsyncJobDTO::make([
            'job_id' => $jobId,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'queued',
            'estimated_time' => $this->estimateProcessingTime($dto->file->getSize()),
        ]);
    }

    /**
     * Estimate processing time based on file size
     *
     * @param int $fileSize File size in bytes
     * @return int Estimated time in seconds
     */
    private function estimateProcessingTime(int $fileSize): int
    {
        // Estimate: 1MB = 2 seconds processing time
        $megabytes = $fileSize / (1024 * 1024);
        return (int) ($megabytes * 2);
    }

    /**
     * Cancel queued or processing job
     *
     * @param string $jobId Job identifier
     * @return bool Success status
     */
    public function cancelJob(string $jobId): bool
    {
        // Delegate to async processing service
        return $this->processingService->cancelProcessing($jobId);
    }

    /**
     * Get job processing progress
     *
     * @param string $jobId Job identifier
     * @return array|null Progress data or null if not found
     */
    public function getJobProgress(string $jobId): ?array
    {
        return $this->processingService->getProcessingProgress($jobId);
    }

    /**
     * Get job processing result
     *
     * @param string $jobId Job identifier
     * @return array|null Result data or null if not found
     */
    public function getJobResult(string $jobId): ?array
    {
        return $this->processingService->getProcessingResult($jobId);
    }
}
