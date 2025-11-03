<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfScorm\DTO\ProgressContext;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use OnixSystemsPHP\HyperfScorm\Service\ScormPackageProcessor;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asynchronous job for processing SCORM packages
 *
 * Responsibilities:
 * - Create UploadedFile from temp path
 * - Create ProgressContext for tracking
 * - Delegate to ScormPackageProcessor
 * - Handle errors and cleanup
 *
 * Note: Progress tracking is now handled by ScormPackageProcessor
 * with ProgressTracker (clean separation of concerns)
 */
class ProcessScormPackageJob extends Job
{
    public const QUEUE_NAME = 'scorm-processing';

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
        $packageProcessor = $container->get(ScormPackageProcessor::class);

        try {
            $logger->info('Starting SCORM package processing', [
                'job_id' => $this->jobId,
                'user_id' => $this->userId,
                'file_size' => $this->fileSize,
                'original_filename' => $this->originalFilename,
            ]);

            $uploadedFile = $this->createUploadedFileFromPath();

            $progressContext = ProgressContext::make([
                'jobId' => $this->jobId,
                'userId' => $this->userId,
                'fileSize' => $this->fileSize,
                'isRetryable' => true, // Job can retry (attempts 1-2 of 3)
            ]);

            $scormPackage = $packageProcessor->process(
                ScormUploadDTO::make([
                    'file' => $uploadedFile,
                    ...$this->metadata,
                ]),
                $progressContext
            );

            $logger->info('SCORM package processing completed', [
                'job_id' => $this->jobId,
                'package_id' => $scormPackage->id,
                'title' => $scormPackage->title,
                'memory_peak' => memory_get_peak_usage(true),
            ]);

        } catch (Throwable $e) {
            $logger->warning('SCORM processing attempt failed (may retry)', [
                'job_id' => $this->jobId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            throw $e;
        }
    }

    private function createUploadedFileFromPath(): UploadedFile
    {
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
}
