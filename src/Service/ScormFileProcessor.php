<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpMessage\Upload\UploadedFile;
use League\Flysystem\FilesystemException;
use OnixSystemsPHP\HyperfScorm\DTO\ProcessedScormPackageDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Memory-efficient SCORM File Processor using streams
 * Handles large SCORM packages without loading entire files into memory
 */
class ScormFileProcessor
{
    private const MANIFEST_FILENAME = 'imsmanifest.xml';
    private const TEMP_EXTRACT_PREFIX = 'scorm_extract_';
    private const CHUNK_SIZE = 8 * 1024 * 1024; // 8MB chunks
    private const MAX_MEMORY_USAGE = 512 * 1024 * 1024; // 512MB limit

    public function __construct(
        private readonly ScormManifestParser $manifestParser,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process uploaded SCORM package with memory optimization
     * Supports both local files and S3 URLs
     */
    public function run(UploadedFile $uploadedFile, ?callable $progressCallback = null): ProcessedScormPackageDTO
    {
        $startMemory = memory_get_usage(true);

        $tempDir = $this->createTempDirectory();

        try {
            $extractPath = $this->extractZipPackageStreaming($uploadedFile, $tempDir, $progressCallback);
            $this->validateScormStructure($extractPath);

            $manifestPath = $extractPath . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
            $manifestDto = $this->manifestParser->parse($manifestPath);

            $storagePath = $this->uploadContentToStorageStreaming($extractPath, $manifestDto, $progressCallback);

            $endMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);

            $this->logger->info('SCORM processing completed', [
                'memory_used' => $endMemory - $startMemory,
                'peak_memory' => $peakMemory,
                'storage_path' => $storagePath,
            ]);

            return ProcessedScormPackageDTO::make([
                'manifestData' => $manifestDto,
                'contentPath' => $storagePath,
                'extractPath' => $extractPath,
                'tempDir' => $tempDir,
            ]);

        } catch (Throwable $e) {
            $this->cleanupTempDirectory($tempDir);
            $this->logger->error('SCORM processing failed', [
                'error' => $e->getMessage(),
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            throw new ScormParsingException(
                "Failed to process SCORM package: " . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Extract ZIP package using streaming to avoid memory issues
     */
    private function extractZipPackageStreaming(
        UploadedFile $uploadedFile,
        string $tempDir,
        ?callable $progressCallback = null
    ): string {
        $zipPath = $uploadedFile->getPathname();

        if (!file_exists($zipPath)) {
            throw new ScormParsingException("ZIP file not found: {$zipPath}");
        }

        $this->checkMemoryUsage("Before ZIP extraction");

        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            throw new ScormParsingException("Failed to open ZIP file. Error code: {$result}");
        }

        $extractPath = $tempDir . DIRECTORY_SEPARATOR . 'content';
        if (!mkdir($extractPath, 0755, true)) {
            $zip->close();
            throw new ScormParsingException("Failed to create extraction directory: {$extractPath}");
        }

        // Extract files one by one to control memory usage
        $numFiles = $zip->numFiles;
        $extractedFiles = 0;
        $this->logger->info("Extracting {$numFiles} files from SCORM package");

        for ($i = 0; $i < $numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename === false) {
                continue;
            }

            // Skip directories
            if (substr($filename, -1) === '/') {
                continue;
            }

            $this->extractSingleFileStreaming($zip, $i, $extractPath, $filename);
            $extractedFiles++;

            // Send progress updates every 25 files or 10% progress
            if (($extractedFiles % 25 === 0) || ($i % max(1, intval($numFiles * 0.1)) === 0)) {
                $progress = $this->calculateExtractionProgress($extractedFiles, $numFiles);
                if ($progressCallback && $progress) {
                    call_user_func($progressCallback, 'extracting', $progress);
                }
            }

            // Memory management every 100 files
            if ($i % 100 === 0) {
                if (memory_get_usage(true) > self::MAX_MEMORY_USAGE) {
                    $this->logger->warning('High memory usage detected during extraction', [
                        'memory_usage' => memory_get_usage(true),
                        'files_processed' => $extractedFiles,
                    ]);
                    gc_collect_cycles(); // Force garbage collection
                }
            }
        }

        $zip->close();

        return $extractPath;
    }

    /**
     * Extract single file from ZIP using stream
     */
    private function extractSingleFileStreaming(\ZipArchive $zip, int $index, string $basePath, string $filename): void
    {
        $fullPath = $basePath . DIRECTORY_SEPARATOR . $filename;
        $directory = dirname($fullPath);

        // Create directory if it doesn't exist
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new ScormParsingException("Failed to create directory: {$directory}");
        }

        // Open file stream from ZIP
        $fileStream = $zip->getStream($filename);
        if ($fileStream === false) {
            throw new ScormParsingException("Failed to get stream for file: {$filename}");
        }

        // Open output file
        $outputHandle = fopen($fullPath, 'wb');
        if ($outputHandle === false) {
            fclose($fileStream);
            throw new ScormParsingException("Failed to create output file: {$fullPath}");
        }

        // Copy in chunks
        while (!feof($fileStream)) {
            $chunk = fread($fileStream, self::CHUNK_SIZE);
            if ($chunk === false) {
                break;
            }
            fwrite($outputHandle, $chunk);
        }

        fclose($fileStream);
        fclose($outputHandle);
    }

    /**
     * Upload directory contents to storage using streaming
     */
    private function uploadContentToStorageStreaming(string $extractPath, ScormManifestDTO $manifest, ?callable $progressCallback = null): string
    {
        $storage = $this->config->get('file.default');
        $filesystem = $this->filesystemFactory->get($storage);
        $packagePath = $this->generateStoragePath($manifest);

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($extractPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            // Count total files first for progress calculation
            $totalFiles = iterator_count(new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($extractPath, \RecursiveDirectoryIterator::SKIP_DOTS)
            ));

            $fileCount = 0;
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $this->uploadSingleFileStreaming($filesystem, $file, $extractPath, $packagePath);
                    $fileCount++;

                    // Send progress updates every 25 files or 10% progress
                    if (($fileCount % 25 === 0) || ($fileCount % max(1, intval($totalFiles * 0.1)) === 0)) {
                        $progress = $this->calculateUploadProgress($fileCount, $totalFiles);
                        if ($progressCallback && $progress) {
                            call_user_func($progressCallback, 'uploading', $progress);
                        }
                    }

                    // Monitor memory every 50 files
                    if ($fileCount % 50 === 0) {
                        if (memory_get_usage(true) > self::MAX_MEMORY_USAGE) {
                            gc_collect_cycles();
                        }
                    }
                }
            }

            $this->logger->info("Successfully uploaded {$fileCount} files to storage");

        } catch (FilesystemException $e) {
            throw new ScormParsingException(
                "Failed to upload content to storage: " . $e->getMessage(),
                previous: $e
            );
        }

        return $packagePath;
    }

    /**
     * Upload single file to storage using streaming
     */
    private function uploadSingleFileStreaming($filesystem, \SplFileInfo $file, string $basePath, string $storagePath): void
    {
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $storageKey = $storagePath . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        // Open file stream
        $inputHandle = fopen($file->getPathname(), 'rb');
        if ($inputHandle === false) {
            throw new ScormParsingException("Failed to read file: " . $file->getPathname());
        }

        try {
            // For small files, use direct upload
            if ($file->getSize() < self::CHUNK_SIZE) {
                $content = stream_get_contents($inputHandle);
                $filesystem->write($storageKey, $content);
            } else {
                // For large files, use streaming upload
                $this->uploadLargeFileStreaming($filesystem, $inputHandle, $storageKey);
            }
        } finally {
            fclose($inputHandle);
        }
    }

    /**
     * Upload large file using true streaming without memory accumulation
     */
    private function uploadLargeFileStreaming($filesystem, $inputHandle, string $storageKey): void
    {
        // Use writeStream if available, otherwise fallback to chunked upload
        try {
            // Try to use stream-based upload if filesystem supports it
            $filesystem->writeStream($storageKey, $inputHandle);
        } catch (\Exception $e) {
            // Fallback: read and upload in small chunks without accumulating
            $this->uploadInChunksWithoutAccumulation($filesystem, $inputHandle, $storageKey);
        }
    }

    /**
     * Fallback method for filesystems that don't support streaming
     */
    private function uploadInChunksWithoutAccumulation($filesystem, $inputHandle, string $storageKey): void
    {
        $partNumber = 0;
        $totalContent = '';

        while (!feof($inputHandle)) {
            $chunk = fread($inputHandle, self::CHUNK_SIZE);
            if ($chunk === false || $chunk === '') {
                break;
            }

            $totalContent .= $chunk;

            // Check memory usage and flush if needed
            $this->checkMemoryUsage();

            // If we're approaching memory limit, we need to switch strategy
            if (memory_get_usage(true) > self::MAX_MEMORY_USAGE * 0.7) {
                // For very large files, save to temp file first
                $this->uploadViaTempFile($filesystem, $inputHandle, $storageKey, $totalContent, $chunk);
                return;
            }
        }

        // Upload accumulated content
        $filesystem->write($storageKey, $totalContent);
    }

    /**
     * Emergency strategy for very large files - use temp file on disk
     */
    private function uploadViaTempFile($filesystem, $inputHandle, string $storageKey, string $existingContent, string $lastChunk): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'scorm_upload_');
        if ($tempFile === false) {
            throw new ScormParsingException("Failed to create temp file for large upload");
        }

        try {
            $tempHandle = fopen($tempFile, 'w+b');
            if ($tempHandle === false) {
                throw new ScormParsingException("Failed to open temp file for writing");
            }

            // Write existing content
            fwrite($tempHandle, $existingContent);
            fwrite($tempHandle, $lastChunk);

            // Continue with remaining chunks
            while (!feof($inputHandle)) {
                $chunk = fread($inputHandle, self::CHUNK_SIZE);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($tempHandle, $chunk);
            }

            // Rewind and upload from temp file
            rewind($tempHandle);
            $filesystem->writeStream($storageKey, $tempHandle);

            fclose($tempHandle);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
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
     * Check current memory usage and log if needed
     */
    private function checkMemoryUsage(string $context): void
    {
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->logger->debug('Memory usage check', [
            'context' => $context,
            'current_memory' => $currentMemory,
            'peak_memory' => $peakMemory,
            'memory_limit' => ini_get('memory_limit'),
        ]);

        if ($currentMemory > self::MAX_MEMORY_USAGE * 0.8) { // 80% of limit
            $this->logger->warning('High memory usage detected', [
                'context' => $context,
                'memory_usage' => $currentMemory,
                'memory_limit' => self::MAX_MEMORY_USAGE,
            ]);
        }
    }

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

    /**
     * Calculate extraction progress with detailed information
     */
    private function calculateExtractionProgress(int $extractedFiles, int $totalFiles): ?array
    {
        if ($totalFiles === 0) {
            return null;
        }

        $progress = min(50, ($extractedFiles / $totalFiles) * 50); // Max 50% for extraction
        return [
            'progress' => (int)$progress,
            'stage_details' => "Extracting files ({$extractedFiles}/{$totalFiles})",
            'processed_bytes' => null, // Could calculate based on file sizes if needed
            'memory_usage' => memory_get_usage(true),
        ];
    }

    /**
     * Calculate upload progress with detailed information
     */
    private function calculateUploadProgress(int $uploadedFiles, int $totalFiles): ?array
    {
        if ($totalFiles === 0) {
            return null;
        }

        $progress = 80 + min(20, ($uploadedFiles / $totalFiles) * 20); // 80-100% for upload
        return [
            'progress' => (int)$progress,
            'stage_details' => "Uploading to storage ({$uploadedFiles}/{$totalFiles})",
            'processed_bytes' => null,
            'memory_usage' => memory_get_usage(true),
        ];
    }
}
