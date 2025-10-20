<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use function Hyperf\Config\config;

/**
 * Core SCORM package processor - handles validation, processing, and storage
 * Unified service for both sync and async upload paths
 */
#[Service]
class ScormPackageProcessor
{
    public function __construct(
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ScormPackageRepository $scormPackageRepository,
    ) {
    }

    #[Transactional(attempts: 1)]
    public function process(ScormUploadDTO $dto): ScormPackage
    {
        $this->validateUploadedFile($dto->file);

        $processedPackage = $this->fileProcessor->run($dto->file);

        $package = $this->scormPackageRepository->create([
            'title' => $dto->title ?? $processedPackage->manifestData->title ?? 'Untitled SCORM Package',
            'description' => $dto->description,
            'scorm_version' => $processedPackage->manifestData->version,
            'content_path' => $processedPackage->contentPath,
            'original_filename' => $dto->file->getClientFilename(),
            'file_size' => $dto->file->getSize(),
            'file_hash' => hash_file('sha256', $dto->file->getPath()),
            'manifest_data' => $processedPackage->manifestData,
            'is_active' => true,
        ]);

        $this->scormPackageRepository->save($package);
        $processedPackage->cleanup();

        return $package;
    }

    private function validateUploadedFile(UploadedFile $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("File upload error: " . $file->getError());
        }

        $allowedExtensions = config('scorm.upload.allowed_extensions', ['zip']);
        if (!in_array($file->getExtension(), $allowedExtensions)) {
            throw new \InvalidArgumentException(
                "Invalid file extension. Allowed: " . implode(', ', $allowedExtensions)
            );
        }

        $maxSize = config('scorm.upload.max_upload_size', 600 * 1024 * 1024); // 100MB default
        if ($file->getSize() > $maxSize) {
            $maxSizeMB = $maxSize / (1024 * 1024);
            throw new \InvalidArgumentException(
                "File size exceeds maximum allowed size of {$maxSizeMB}MB"
            );
        }

        $allowedMimeTypes = config('scorm.upload.allowed_mime_types', [
            'application/zip',
            'application/x-zip-compressed',
        ]);
        if (!empty($allowedMimeTypes) && !in_array($file->getMimeType(), $allowedMimeTypes)) {
            throw new \InvalidArgumentException(
                "Invalid file type. Expected ZIP file, got: " . $file->getMimeType()
            );
        }
    }
}
