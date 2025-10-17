<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use OnixSystemsPHP\HyperfScorm\Service\AsyncUploadScormPackageService;
use OnixSystemsPHP\HyperfScorm\Service\ScormJobStatusService;
use OnixSystemsPHP\HyperfScorm\Service\ScormTempFileService;
use OnixSystemsPHP\HyperfScorm\Service\ScormWebSocketNotificationService;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asynchronous job for processing SCORM packages
 * Uses streaming processor to handle large files efficiently
 */
class ProcessScormPackageJob extends Job
{
    public const QUEUE_NAME = 'scorm-processing';

    protected int $maxAttempts = 3;
    protected int $delay = 0;

    public function __construct(
        private readonly string $jobId,
        private readonly string $tempFilePath,
        private readonly string $originalFilename,
        private readonly int $fileSize,
        private readonly int $userId,
        private readonly array $metadata = []
    ) {
    }

    public function handle(): void
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerInterface::class);
        $asyncUploadScormPackageService = $container->get(AsyncUploadScormPackageService::class);
        $jobStatusService = $container->get(ScormJobStatusService::class);
        $tempFileService = $container->get(ScormTempFileService::class);
        $wsService = $container->get(ScormWebSocketNotificationService::class);

        try {
            $progressData = [
                'status' => 'processing',
                'progress' => 0,
                'stage' => 'initializing',
                'stage_details' => 'Preparing SCORM package for processing...',
                'started_at' => time(),
                'file_size' => $this->fileSize,
                'memory_usage' => memory_get_usage(true),
                'processed_bytes' => 0,
            ];
            $jobStatusService->updateProgress($this->jobId, $progressData);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $progressData);

            $uploadedFile = $this->createUploadedFileFromPath();

            $extractingData = [
                'status' => 'processing',
                'progress' => 10,
                'stage' => 'extracting',
                'stage_details' => 'Extracting files from SCORM package...',
                'memory_usage' => memory_get_usage(true),
                'file_size' => $this->fileSize,
                'processed_bytes' => (int)($this->fileSize * 0.1),
            ];
            $jobStatusService->updateProgress($this->jobId, $extractingData);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $extractingData);

//            $progressCallback = function (string $stage, array $progressData) use ($jobStatusService, $wsService) {
//                $this->handleProgressCallback($stage, $progressData, $jobStatusService, $wsService);
//            };

            $scormPackage = $asyncUploadScormPackageService->run(UploadPackageDTO::make([
                'file' => $uploadedFile,
                ...$this->metadata,
            ]));

            $completedData = [
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'stage_details' => 'SCORM package processing completed successfully',
                'completed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
                'file_size' => $this->fileSize,
                'processed_bytes' => $this->fileSize,
                'package_id' => $scormPackage->id,
                'title' => $scormPackage->title,
            ];
            $jobStatusService->updateProgress($this->jobId, $completedData);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $completedData);
        } catch (Throwable $e) {
            $logger->error('SCORM package processing job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            // Update progress - failed (send WebSocket notification)
            $failedData = [
                'status' => 'failed',
                'progress' => 0,
                'stage' => 'failed',
                'stage_details' => 'Processing failed: ' . substr($e->getMessage(), 0, 100),
                'error' => $e->getMessage(),
                'failed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
                'file_size' => $this->fileSize,
            ];
            $jobStatusService->updateProgress($this->jobId, $failedData);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $failedData);

            // Save error result
            $jobStatusService->setResult($this->jobId, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
                'original_filename' => $this->originalFilename,
                'failed_at' => time(),
            ]);

            // Cleanup temp file
            $tempFileService->cleanup($this->tempFilePath, $this->jobId);

            throw $e;
        }
    }

    public function failed(Throwable $throwable): void
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerInterface::class);
        $jobStatusService = $container->get(ScormJobStatusService::class);
        $tempFileService = $container->get(ScormTempFileService::class);

        $logger->error('SCORM processing job permanently failed', [
            'job_id' => $this->jobId,
            'error' => $throwable->getMessage(),
        ]);

        // Mark as permanently failed
        $jobStatusService->updateProgress($this->jobId, [
            'status' => 'failed',
            'progress' => 0,
            'stage' => 'permanently_failed',
            'error' => $throwable->getMessage(),
            'failed_at' => time(),
        ]);

        $jobStatusService->setResult($this->jobId, [
            'status' => 'permanently_failed',
            'error' => $throwable->getMessage(),
            'user_id' => $this->userId,
            'original_filename' => $this->originalFilename,
            'failed_at' => time(),
        ]);

        // Cleanup temp file
        $tempFileService->cleanup($this->tempFilePath, $this->jobId);
    }

    /**
     * Create UploadedFile instance from temporary file path
     */
    private function createUploadedFileFromPath(): UploadedFile
    {
        // Since we now use original uploaded file paths, validate existence
        if (!file_exists($this->tempFilePath)) {
            throw new ScormParsingException("Uploaded file not found: {$this->tempFilePath}");
        }

        return new UploadedFile(
            $this->tempFilePath,
            $this->fileSize,
            UPLOAD_ERR_OK,
            $this->originalFilename
        );
    }


    /**
     * Handle progress callback from ScormFileProcessor
     */
    private function handleProgressCallback(
        string $stage,
        array $progressData,
        ScormJobStatusService $jobStatusService,
        ScormWebSocketNotificationService $wsService
    ): void {
        $fullProgressData = array_merge($progressData, [
            'status' => 'processing',
            'stage' => $stage,
            'file_size' => $this->fileSize,
        ]);

        $jobStatusService->updateProgress($this->jobId, $fullProgressData);
        $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $fullProgressData);
    }
}
