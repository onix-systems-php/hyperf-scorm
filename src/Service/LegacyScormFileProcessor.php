<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpMessage\Upload\UploadedFile;
use League\Flysystem\FilesystemException;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\Entity\ProcessedScormPackage;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;

/**
 * SCORM File Processor - handles SCORM ZIP package processing
 * Integrates with hyperf-file-upload for file handling
 */
class LegacyScormFileProcessor
{
    private const MANIFEST_FILENAME = 'imsmanifest.xml';
    private const TEMP_EXTRACT_PREFIX = 'scorm_extract_';

    public function __construct(
        private readonly ScormManifestParser $manifestParser,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * Process uploaded SCORM package
     *
     * @throws ScormParsingException
     */
    public function run(UploadedFile $uploadedFile): ProcessedScormPackage
    {
        $tempDir = $this->createTempDirectory();

        try {
            $extractPath = $this->extractZipPackage($uploadedFile, $tempDir);

            $this->validateScormStructure($extractPath);

            $manifestPath = $extractPath . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            $manifestDto = $this->manifestParser->parse($manifestPath);

            $storagePath = $this->uploadContentToStorage($extractPath, $manifestDto);

            return new ProcessedScormPackage(
                manifestData: $manifestDto,
                contentPath: $storagePath,
                extractPath: $extractPath,
                tempDir: $tempDir
            );
        } catch (\Exception $e) {
            $this->cleanupTempDirectory($tempDir);
            throw new ScormParsingException(
                "Failed to process SCORM package: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Create temporary directory for extraction
     */
    private function createTempDirectory(): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::TEMP_EXTRACT_PREFIX . uniqid();

        if (!mkdir($tempDir, 0755, true)) {
            throw new ScormParsingException("Failed to create temp directory: {$tempDir}");
        }

        return $tempDir;
    }

    /**
     * Extract ZIP package to temporary directory
     */
    private function extractZipPackage(UploadedFile $uploadedFile, string $tempDir): string
    {
        $zipPath = $uploadedFile->getPathname();

        if (!file_exists($zipPath)) {
            throw new ScormParsingException("ZIP file not found: {$zipPath}");
        }

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new ScormParsingException("Failed to open ZIP file. Error code: {$result}");
        }

        $extractPath = $tempDir . DIRECTORY_SEPARATOR . 'content';

        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new ScormParsingException("Failed to extract ZIP file to: {$extractPath}");
        }

        $zip->close();

        return $extractPath;
    }

    /**
     * Validate SCORM package structure
     */
    private function validateScormStructure(string $extractPath): void
    {
        $manifestPath = $extractPath . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;

        if (!file_exists($manifestPath)) {
            throw new ScormParsingException(
                "Invalid SCORM package: " . self::MANIFEST_FILENAME . " not found"
            );
        }

        if (!is_readable($manifestPath)) {
            throw new ScormParsingException(
                "Manifest file is not readable: {$manifestPath}"
            );
        }
    }

    /**
     * Upload extracted content to configured storage
     */
    private function uploadContentToStorage(string $extractPath, ScormManifestDTO $manifest): string
    {
//        $storage = $this->config->get('scorm.storage.default', 's3');
        $storage = $this->config->get('file.default');
        $filesystem = $this->filesystemFactory->get($storage);

        $packagePath = $this->generateStoragePath($manifest);

        try {
            $this->uploadDirectoryRecursively($filesystem, $extractPath, $packagePath);
        } catch (FilesystemException $e) {
            throw new ScormParsingException(
                "Failed to upload content to storage: " . $e->getMessage(),
                previous: $e
            );
        }

        return $packagePath;
    }

    /**
     * Generate unique storage path for SCORM package
     */
    private function generateStoragePath(ScormManifestDTO $manifest): string
    {
        $basePath = $this->config->get('scorm.storage.base_path', 'scorm-packages');
        $timestamp = date('Y/m/d');
        $uniqueId = uniqid();

        return "{$basePath}/{$timestamp}/{$uniqueId}";
    }

    /**
     * Upload directory contents recursively to storage
     */
    private function uploadDirectoryRecursively($filesystem, string $localPath, string $storagePath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($localPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $storageKey = $storagePath . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

                $content = file_get_contents($file->getPathname());
                if ($content === false) {
                    throw new ScormParsingException("Failed to read file: " . $file->getPathname());
                }

                $filesystem->write($storageKey, $content);
            }
        }
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTempDirectory(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $this->removeDirectoryRecursively($tempDir);
        }
    }

    /**
     * Remove directory and all contents recursively
     */
    private function removeDirectoryRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * Get public URL for SCORM content
     */
    public function getPublicUrl(string $contentPath, string $relativePath = ''): string
    {
//        $storage = $this->config->get('scorm.storage.default', 's3');
        $storage = $this->config->get('file.default', 's3');
        $baseUrl = $this->config->get("scorm.storage.{$storage}.public_url", '');

        $fullPath = rtrim($contentPath, '/') . '/' . ltrim($relativePath, '/');
        return rtrim($baseUrl, '/') . '/' . ltrim($fullPath, '/');
    }

    /**
     * Check if file exists in storage
     */
    public function fileExistsInStorage(string $contentPath, string $relativePath): bool
    {
        $storage = $this->config->get('scorm.storage.default', 's3');
        $filesystem = $this->filesystemFactory->get($storage);

        $filePath = rtrim($contentPath, '/') . '/' . ltrim($relativePath, '/');

        try {
            return $filesystem->fileExists($filePath);
        } catch (FilesystemException) {
            return false;
        }
    }
}
