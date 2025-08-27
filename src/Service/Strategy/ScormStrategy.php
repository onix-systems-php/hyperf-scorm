<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service\Strategy;

use App\Common\Constants\FileStorageTypes;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use App\Course\Service\CourseContent\Strategy\ContentStrategyInterface;
use App\Course\ValueObject\ScormFile;
use Hyperf\Contract\ConfigInterface;
use Hyperf\DbConnection\Annotation\Transactional;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpMessage\Upload\UploadedFile;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use OnixSystemsPHP\HyperfCore\Constants\ErrorCode;
use OnixSystemsPHP\HyperfCore\Exception\BusinessException;
use function Hyperf\Translation\__;

class ScormStrategy
{

    private $localFilesystem = null;

    public function __construct(
        private FilesystemFactory $fileSystemFactory,
        private ConfigInterface $config,
    ) {
        $this->localFilesystem = $this->fileSystemFactory->get(FileStorageTypes::TMP);
    }

    /**
     * @throws \Exception
     * @throws FilesystemException
     */
    #[Transactional(attempts: 1)]
    public function run(UploadPackageDTO $uploadScormDTO): array
    {
        $this->validate($uploadScormDTO->file);

        $scormFile = ScormFile::fromArray([
            'storage' => FileStorageTypes::TMP,
            'path' => $uploadScormDTO->file->getPath(),
            'full_path' => $uploadScormDTO->file->getPathname(),
        ]);

        if (! $scormFile->isValid() || ! $scormFile->extract()) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.file.scorn_package_issue')
            );
        }

        $storage = $this->config->get('file.default');
        [$path, $publicPath] = $this->getStoragePath($scormFile, $storage);
        $this->uploadDirectory($scormFile->getExtractDir(), $storage, $path);

        $this->localFilesystem->deleteDirectory($scormFile->getExtractDir());

        $domain = $this->config->get("course_content_upload.storage.{$storage}.domain");

        return [
            'path' => $path,
            'url' =>  $domain . $publicPath . DIRECTORY_SEPARATOR . $scormFile->findLaunchFile(),
            'storage' => $storage,
            'domain' => $domain,
        ];
    }

    private function validate(UploadedFile $uploadedFile): void
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.file.upload_issue')
            );
        }

        if (! in_array($uploadedFile->getMimeType(), $this->config->get('course_content_upload.scorm_mime_types'))) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.file.mime_type_issue')
            );
        }
    }

    private function getStoragePath(ScormFile $scormFile, string $storage): array
    {
        $storagePath = $this->config->get("course_content_upload.storage.{$storage}.storage_path_prefix", '');

        $path = $storagePath . DIRECTORY_SEPARATOR . $scormFile->getExtractDir();
        $publicPath =  $storagePath . DIRECTORY_SEPARATOR . $scormFile->getExtractDir();

        return [$path, $publicPath];
    }


    private function uploadDirectory(string $directoryPath, string $storage, string $keyPrefix = '/'): void
    {
        $files = $this->localFilesystem->listContents($directoryPath, true);
        $filesystem = $this->fileSystemFactory->get($storage);
        foreach ($files as $file) {
            if ($file instanceof FileAttributes && $file->isFile()) {
                $this->uploadFile($filesystem, $file, $keyPrefix, $directoryPath);
            }
        }
    }

    private function uploadFile(
        Filesystem $filesystem,
        FileAttributes $file,
        string $keyPrefix,
        string $directoryPath
    ): void {
        $relativePath = $this->getRelativePath($file->path(), $directoryPath);
        $key = $this->getKeyWithPrefix($keyPrefix, $relativePath);
        $stream = $this->localFilesystem->readStream($file->path());

        if ($stream === false) {
            throw new \RuntimeException("Failed to open stream for file: {$file->path()}");
        }

        try {
            $filesystem->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function getRelativePath(string $filePath, string $directoryPath): string
    {
        return ltrim(str_replace($directoryPath, '', $filePath), '/');
    }

    private function getKeyWithPrefix(string $keyPrefix, string $relativePath): string
    {
        return ltrim($keyPrefix, '/') . DIRECTORY_SEPARATOR . $relativePath;
    }
}
