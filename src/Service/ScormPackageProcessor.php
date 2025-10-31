<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ProgressContext;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use Psr\Log\LoggerInterface;
use Throwable;
use function Hyperf\Config\config;

/**
 * Core SCORM package processor - handles validation, processing, and storage
 * Unified service for both sync and async upload paths
 *
 * Variant A Architecture: Processor with Optional ProgressTracker
 * - Clean separation: business logic vs progress tracking
 * - Optional tracking: sync paths don't need progress updates
 * - Dependency injection: tracker is optional through constructor
 * - Error handling: Retry-aware notifications (don't notify on retryable failures)
 */
#[Service]
class ScormPackageProcessor
{

    private const ALLOWED_EXTENSIONS = ['zip'];
    private const ALLOWED_MIME_TYPES = [
        'application/zip',
        'application/x-zip-compressed',
    ];

    public function __construct(
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly LoggerInterface $logger,
        private readonly ScormWebSocketNotificationService  $webSocketNotificationService,
        private readonly ScormJobStatusService $scormJobStatusService,
    ) {
    }

    /**
     * Process SCORM package with optional progress tracking
     *
     * Handles errors intelligently based on retry context:
     * - If isRetryable=true: logs error but doesn't notify user (job will retry)
     * - If isRetryable=false: notifies user about permanent failure
     *
     * @param ScormUploadDTO $dto Upload data
     * @param ProgressContext|null $progressContext Optional context for async tracking
     * @return ScormPackage Processed and saved package
     * @throws Throwable Re-throws exceptions for Job to handle retry logic
     */
    #[Transactional(attempts: 1)]
    public function process(ScormUploadDTO $dto, ?ProgressContext $progressContext = null): ScormPackage
    {
        try {
            $this->trackProgress($progressContext, [
                'status' => 'processing',
                'progress' => 0,
                'stage' => 'validating',
                'stage_details' => 'Validating SCORM package...',
            ]);

            $this->validateUploadedFile($dto->file);

            $this->trackProgress($progressContext, [
                'status' => 'processing',
                'progress' => 25,
                'stage' => 'processing',
                'stage_details' => 'Extracting and processing SCORM content...',
            ]);

            $processedPackage = $this->fileProcessor->run($dto->file);

            $this->trackProgress($progressContext, [
                'status' => 'processing',
                'progress' => 90,
                'stage' => 'saving',
                'stage_details' => 'Saving SCORM package to database...',
            ]);

            $package = $this->scormPackageRepository->create([
                'title' => $dto->title ?? $processedPackage->manifestData->title ?? 'Untitled SCORM Package',
                'description' => $dto->description,
                'scorm_version' => $processedPackage->manifestData->version,
                'content_path' => $processedPackage->contentPath,
                'original_filename' => $dto->file->getClientFilename(),
                'file_size' => $dto->file->getSize(),
                'file_hash' => hash_file('sha256', $dto->file->getRealPath()),
                'manifest_data' => $processedPackage->manifestData,
                'is_active' => true,
            ]);

            $this->scormPackageRepository->save($package);
            $processedPackage->cleanup();

            $this->trackProgress($progressContext, [
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'stage_details' => 'SCORM package processing completed successfully',
                'package_id' => $package->id,
            ]);

            return $package;

        } catch (Throwable $e) {
            $this->handleProcessingError($progressContext, $e);

            throw $e;
        }
    }

    private function handleProcessingError(?ProgressContext $context, Throwable $error): void
    {
        if ($context->isRetryable) {
            return;
        }

        $this->trackProgress($context, [
            'status' => 'failed',
            'progress' => 0,
            'stage' => 'failed',
            'stage_details' => 'SCORM package processing failed',
            'error' => $error->getMessage(),
            'failed_at' => time(),
        ]);
    }

    private function trackProgress(?ProgressContext $context, array $progressData): void
    {
        if ($this->scormJobStatusService === null || $context === null) {
            return;
        }

        $this->scormJobStatusService->updateProgress($context->jobId, $progressData);
        $this->webSocketNotificationService->sendUploadProgressUpdate(
            $context->userId,
            $context->jobId,
            $progressData
        );
    }

    private function validateUploadedFile(UploadedFile $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("File upload error: " . $file->getError());
        }

        if (!in_array($file->getExtension(), self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException(
                "Invalid file extension. Allowed: " . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        if ($file->getSize() > config('scorm.upload.max_file_size')) {
            $maxSizeMB = config('scorm.upload.max_file_size') / (1024 * 1024);
            throw new \InvalidArgumentException(
                "File size exceeds maximum allowed size of {$maxSizeMB}MB"
            );
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException(
                "Invalid file type. Expected ZIP file, got: " . $file->getMimeType()
            );
        }
    }
}
