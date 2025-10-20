<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormAsyncJob;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackage;

/**
 * Orchestrator for SCORM package uploads
 * Intelligently routes to sync or async processing based on file size and system load
 */
#[Service]
class ScormUploadOrchestratorService
{
    /**
     * File size threshold for async processing (25MB)
     * Files larger than this will be processed asynchronously
     * Based on analysis: 25MB+ files risk 60s timeout
     */
    private const ASYNC_THRESHOLD_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly ScormPackageProcessor $packageProcessor,
        private readonly ScormAsyncQueueService $asyncQueueService,
    ) {}

    /**
     * Process SCORM package upload
     * Routes to sync or async processing based on file size
     *
     * @param ScormUploadDTO $dto Upload data transfer object
     * @return ResourceScormPackage|ResourceScormAsyncJob Package resource or job status
     */
    public function process(ScormUploadDTO $dto): ResourceScormPackage|ResourceScormAsyncJob
    {
        $fileSize = $dto->file->getSize();

        // Route based on file size
        if ($this->shouldProcessAsync($fileSize)) {
            return $this->processAsync($dto);
        }

        return $this->processSync($dto);
    }

    /**
     * Process synchronously (for small files)
     *
     * @param ScormUploadDTO $dto Upload data transfer object
     * @return ResourceScormPackage Package resource
     */
    private function processSync(ScormUploadDTO $dto): ResourceScormPackage
    {
        $package = $this->packageProcessor->process($dto);
        return ResourceScormPackage::make($package);
    }

    /**
     * Process asynchronously (for large files)
     *
     * @param ScormUploadDTO $dto Upload data transfer object
     * @return ResourceScormAsyncJob Job status resource
     */
    private function processAsync(ScormUploadDTO $dto): ResourceScormAsyncJob
    {
        $jobDTO = $this->asyncQueueService->queueProcessing($dto);
        return ResourceScormAsyncJob::make($jobDTO);
    }

    /**
     * Determine if file should be processed asynchronously
     *
     * @param int $fileSize File size in bytes
     * @return bool True if async processing recommended
     */
    private function shouldProcessAsync(int $fileSize): bool
    {
        return $fileSize >= self::ASYNC_THRESHOLD_BYTES;
    }

    /**
     * Get async threshold in bytes
     *
     * @return int Threshold in bytes
     */
    public function getAsyncThreshold(): int
    {
        return self::ASYNC_THRESHOLD_BYTES;
    }

    /**
     * Check if file will be processed asynchronously
     *
     * @param int $fileSize File size in bytes
     * @return bool True if will use async processing
     */
    public function willUseAsync(int $fileSize): bool
    {
        return $this->shouldProcessAsync($fileSize);
    }
}
