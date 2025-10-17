<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use function Hyperf\Config\config;

#[Service]
class AsyncUploadScormPackageService
{
    public function __construct(
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ScormPackageRepository $scormPackageRepository,
    ) {
    }

    #[Transactional(attempts: 1)]
    public function run(UploadPackageDTO $uploadScormDTO): ScormPackage
    {
//        $this->validateUploadedFile($uploadScormDTO->file);
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

        $processedPackage->cleanup();

        return $package;
    }

    public function validateUploadedFile(UploadedFile $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException("File upload error: " . $file->getError());
        }

        if (!in_array($file->getExtension(), config('scorm.upload.allowed_extensions'))) {
            throw new \InvalidArgumentException("Only ZIP files are allowed");
        }

//        if (!in_array($file->getMimeType(), config('scorm.upload.allowed_mime_types'))) {
//            throw new \InvalidArgumentException("Invalid file type. Expected ZIP file");
//        }

        if ($file->getSize() > config('scorm.upload.max_upload_size')) {
            throw new \InvalidArgumentException("File size exceeds maximum allowed size");
        }
    }
}
