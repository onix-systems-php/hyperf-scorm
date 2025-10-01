<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormScoRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Model\ScormSco;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfCore\Service\Service;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfScorm\Entity\ProcessedScormPackage;


/**
 * Service for uploading SCORM packages from ZIP files
 * Integrates with hyperf-file-upload for file handling
 */
#[Service]
class UploadScormPackageService
{
    public function __construct(
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly ScormScoRepository $scoRepository
    ) {
    }

    /**
     * Upload and process SCORM package
     */
    #[Transactional(attempts: 1)]
    public function run(UploadPackageDTO $uploadScormDTO): ScormPackage
    {

        $this->validateUploadedFile($uploadScormDTO->file);

        $processedPackage = $this->fileProcessor->run($uploadScormDTO->file);

        $package = $this->scormPackageRepository->create([
            'title' => $uploadScormDTO->title ?? $processedPackage->manifestData->title ?? 'Untitled SCORM Package',
            'description' => $uploadScormDTO->description,
//            'identifier' => $processedPackage->manifestData->identifier,
            'scorm_version' => $processedPackage->manifestData->version,
            'content_path' => $processedPackage->contentPath,
            'original_filename' => $uploadScormDTO->file->getClientFilename(),
            'file_size' => $uploadScormDTO->file->getSize(),
            'file_hash' => hash_file('sha256', $uploadScormDTO->file->getPath()),
            'manifest_data' => $processedPackage->manifestData,
            'is_active' => true,
        ]);

        $this->scormPackageRepository->save($package);

        // Create SCOs using repository pattern with createMany for efficiency
//        $this->createScormSco($package, $processedPackage->manifestData);

        $processedPackage->cleanup();

        return $package;
    }

    /**
     * Validate uploaded file
     */
    private function validateUploadedFile(UploadedFile $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("File upload error: " . $file->getError());
        }

        if ($file->getExtension() !== 'zip') {
            throw new \InvalidArgumentException("Only ZIP files are allowed");
        }

//        $maxSize = 100 * 1024 * 1024; // 100MB
//        if ($file->getSize() > $maxSize) {
//            throw new \InvalidArgumentException("File size exceeds maximum allowed size");
//        }

        $mimeType = $file->getMimeType();
        $allowedMimes = ['application/zip', 'application/x-zip-compressed'];
        if (!in_array($mimeType, $allowedMimes)) {
            throw new \InvalidArgumentException("Invalid file type. Expected ZIP file");
        }
    }
}
