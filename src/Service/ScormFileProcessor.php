<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Filesystem\FilesystemFactory;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use OnixSystemsPHP\HyperfScorm\DTO\ProcessedScormPackageDTO;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use OnixSystemsPHP\HyperfScorm\ValueObject\ScormFile;

class ScormFileProcessor
{
    private const TEMP_QUEUE = 'temp-queue';

    private $localFilesystem;

    public function __construct(
        private readonly ScormManifestParser $manifestParser,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly ConfigInterface $config,
    ) {
        $this->localFilesystem = $this->filesystemFactory->get(self::TEMP_QUEUE);
    }

    public function run(ScormFile $scormFile): ProcessedScormPackageDTO
    {
        try {
            $scormFile->extract();
            $manifestDto = $this->manifestParser->parse($scormFile->getManifestPath());
            $storage = $this->config->get('scorm.storage.default');
            [$path, $publicPath, $domain] = $this->getStoragePath($scormFile, $storage);
            $this->uploadDirectory($scormFile->getExtractDir(), $storage, $path);
            $this->localFilesystem->deleteDirectory($scormFile->getExtractDir());

            return ProcessedScormPackageDTO::make([
                'manifestData' => $manifestDto,
                'contentPath' => $publicPath,
                'launcher_path' => $manifestDto->getPrimaryLauncherPath(),
                'domain' => $domain,
            ]);
        } catch (\Exception $exception) {
            throw new ScormParsingException(
                'Failed to process SCORM package: ' . $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $this->localFilesystem->deleteDirectory($scormFile->getExtractDir());
        }
    }

    private function getStoragePath(ScormFile $scormFile, string $storage): array
    {
        $storagePathConfig = $this->config->get("scorm.storage.{$storage}.storage_path_prefix", '');
        $storagePublicConfig = $this->config->get("scorm.storage.{$storage}.public_path_prefix", '');
        $domain = $this->config->get("scorm.storage.{$storage}.domain", '');

        $path = $storagePathConfig . DIRECTORY_SEPARATOR . $scormFile->getExtractDir();
        $publicPath = $storagePublicConfig . DIRECTORY_SEPARATOR . $scormFile->getExtractDir();

        return [$path, $publicPath, $domain];
    }

    private function uploadDirectory(string $directoryPath, string $storage, string $keyPrefix = '/'): void
    {
        $files = $this->localFilesystem->listContents($directoryPath, true);
        $filesystem = $this->filesystemFactory->get($storage);
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

    private function getLauncherPath(string $publicPath, string $launcher_path): string
    {
        return rtrim($publicPath, '/') . DIRECTORY_SEPARATOR . $launcher_path;
    }
}
