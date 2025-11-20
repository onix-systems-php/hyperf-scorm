<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfActionsLog\Event\Action;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ProgressContext;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\ValueObject\ScormFile;
use Psr\EventDispatcher\EventDispatcherInterface;
use function Hyperf\Config\config;

#[Service]
class ScormPackageProcessor
{
    public const ACTION = 'upload_scorm_package';

    private const ALLOWED_EXTENSIONS = ['zip'];

    private const ALLOWED_MIME_TYPES = [
        'application/zip',
        'application/x-zip-compressed',
    ];

    public function __construct(
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ScormProgressTracker $progressTracker,
    ) {
    }

    #[Transactional(attempts: 1)]
    public function process(ScormUploadDTO $dto, ProgressContext $progressContext): ScormPackage
    {
        try {
            $this->progressTracker->track($progressContext, [
                'status' => 'processing',
                'progress' => 35,
                'stage' => 'validating',
                'stage_details' => 'Validating SCORM package...',
            ]);

            $this->validateUploadedFile($dto->file);

            $this->progressTracker->track($progressContext, [
                'status' => 'processing',
                'progress' => 55,
                'stage' => 'processing',
                'stage_details' => 'Extracting and processing SCORM content...',
            ]);

            $scormFile = ScormFile::fromArray([
                'storage' => 'scorm-queue',
                'path' => $dto->file->getPath(),
                'full_path' => $dto->file->getPathname(),
                'extract_dir' => $progressContext->jobId,
            ]);

            $processedPackage = $this->fileProcessor->run($scormFile);

            $this->progressTracker->track($progressContext, [
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
                'domain' => $processedPackage->domain,
                'launcher_path' => $processedPackage->launcher_path,
                'original_filename' => $dto->file->getClientFilename(),
                'file_size' => $dto->file->getSize(),
                'file_hash' => hash_file('sha256', (string)$dto->file->getSize()),
                'manifest_data' => $processedPackage->manifestData,
                'is_active' => true,
            ]);

            $this->scormPackageRepository->save($package);

            $this->eventDispatcher->dispatch(new Action(self::ACTION, $package, $package->toArray()));

            $this->progressTracker->track($progressContext, [
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'stage_details' => 'SCORM package processing completed successfully',
                'package_id' => $package->id,
            ]);

            return $package;
        } catch (\Throwable $error) {
            if (! $progressContext->isRetryable) {
                $this->progressTracker->track($progressContext, [
                    'status' => 'failed',
                    'progress' => 0,
                    'stage' => 'failed',
                    'stage_details' => 'SCORM package processing failed',
                    'error' => $error->getMessage(),
                    'failed_at' => time(),
                ]);
            }

            throw $error;
        }
    }

    private function validateUploadedFile(UploadedFile $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('File upload error: ' . $file->getError());
        }

        if (! in_array($file->getExtension(), self::ALLOWED_EXTENSIONS)) {
            throw new \InvalidArgumentException(
                'Invalid file extension. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS)
            );
        }

        if ($file->getSize() > config('scorm.upload.max_file_size')) {
            $maxSizeMB = config('scorm.upload.max_file_size') / (1024 * 1024);
            throw new \InvalidArgumentException(
                "File size exceeds maximum allowed size of {$maxSizeMB}MB"
            );
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            throw new \InvalidArgumentException(
                'Invalid file type. Expected ZIP file, got: ' . $file->getMimeType()
            );
        }
    }
}
