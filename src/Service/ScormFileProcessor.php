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

class ScormFileProcessor
{
    private const MANIFEST_FILENAME = 'imsmanifest.xml';
    private const CHUNK_SIZE = 8 * 1024 * 1024; // 8MB chunks
    private const TEMP_EXTRACT_PREFIX = 'scorm_extract_';
    private const MEMORY_CHECK_INTERVAL_UPLOAD = 50;
    private const PROGRESS_INTERVAL_FILES = 25;
    private const MEMORY_WARNING_THRESHOLD = 0.8;

    // Security and extraction strategy constants
    private const MAX_PACKAGE_SIZE = 800 * 1024 * 1024; // 800MB maximum
    private const EXTRACT_SIZE_THRESHOLD = 200 * 1024 * 1024; // 200MB threshold for strategy selection
    private const MAX_COMPRESSION_RATIO = 1000; // Maximum allowed compression ratio
    private const HIGH_COMPRESSION_WARNING_RATIO = 100; // Warning threshold for compression ratio

    private readonly int $maxMemoryUsage;

    public function __construct(
        private readonly ScormManifestParser $manifestParser,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
        $this->maxMemoryUsage = $this->config->get('scorm.performance.max_memory_usage', 512 * 1024 * 1024);
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
     * Extract ZIP package with automatic strategy selection based on size
     *
     * Strategy:
     * - < 200MB: Use fast extractTo() method
     * - >= 200MB: Use memory-safe streaming extraction
     *
     * @throws ScormParsingException
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

        try {
            $extractPath = $tempDir . DIRECTORY_SEPARATOR . 'content';

            // STEP 1: Security validation (critical - protects against path traversal, zip bombs)
            $packageInfo = $this->validateZipSecurity($zip, $extractPath);

            // Create extraction directory
            if (!mkdir($extractPath, 0755, true) && !is_dir($extractPath)) {
                throw new ScormParsingException("Failed to create extraction directory: {$extractPath}");
            }

            $numFiles = $zip->numFiles;
            $totalSize = $packageInfo['total_size'];

            $this->logger->info('ZIP package analysis', [
                'files' => $numFiles,
                'size_mb' => round($totalSize / 1024 / 1024, 2),
                'strategy' => $totalSize < self::EXTRACT_SIZE_THRESHOLD ? 'native' : 'streaming',
            ]);

            // STEP 2: Choose extraction strategy based on package size
            return $this->extractUsingStreamingMethod($zip, $extractPath, $numFiles, $progressCallback);

        } finally {
            $zip->close();
        }
    }

    /**
     * Security validation for ZIP contents
     *
     * Protects against:
     * - Path traversal attacks (../ in filenames)
     * - Zip bombs (packages > 800MB)
     * - Malicious filenames with special characters
     * - Suspicious compression ratios
     *
     * @return array{total_size: int, file_count: int}
     * @throws ScormParsingException on security violation
     */
    private function validateZipSecurity(\ZipArchive $zip, string $extractPath): array
    {
        $numFiles = $zip->numFiles;
        $totalUncompressedSize = 0;
        $realExtractPath = realpath(dirname($extractPath)) ?: dirname($extractPath);

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $filename = $stat['name'];

            // 1. Path traversal protection
            if (str_contains($filename, '..')) {
                throw new ScormParsingException(
                    "Security violation: Path traversal detected in filename: {$filename}"
                );
            }

            // 2. Absolute path protection
            if (str_starts_with($filename, '/') || preg_match('/^[a-zA-Z]:[\\\\\\/]/', $filename)) {
                throw new ScormParsingException(
                    "Security violation: Absolute path detected in filename: {$filename}"
                );
            }

            // 3. Boundary validation
            $targetPath = $extractPath . DIRECTORY_SEPARATOR . $filename;
            $realTargetPath = realpath(dirname($targetPath));

            if ($realTargetPath && !str_starts_with($realTargetPath, $realExtractPath)) {
                throw new ScormParsingException(
                    "Security violation: File would extract outside target directory: {$filename}"
                );
            }

            // 4. Size limit enforcement (800MB max)
            $totalUncompressedSize += $stat['size'];
            if ($totalUncompressedSize > self::MAX_PACKAGE_SIZE) {
                $sizeMB = round($totalUncompressedSize / 1024 / 1024, 2);
                $limitMB = round(self::MAX_PACKAGE_SIZE / 1024 / 1024, 2);
                throw new ScormParsingException(
                    "Security violation: Package size {$sizeMB}MB exceeds limit {$limitMB}MB"
                );
            }

            // 5. Compression ratio check (detect zip bombs and suspicious files)
            if ($stat['comp_size'] > 0) {
                $ratio = $stat['size'] / $stat['comp_size'];

                // Reject extremely high compression ratios
                if ($ratio > self::MAX_COMPRESSION_RATIO) {
                    throw new ScormParsingException(
                        "Security violation: Suspicious compression ratio (" . round($ratio, 2) . ":1) for file: {$filename}"
                    );
                }

                // Warning for high (but not extreme) compression ratios
                if ($ratio > self::HIGH_COMPRESSION_WARNING_RATIO) {
                    $this->logger->warning('High compression ratio detected', [
                        'file' => $filename,
                        'ratio' => round($ratio, 2),
                        'compressed_size' => $stat['comp_size'],
                        'uncompressed_size' => $stat['size'],
                    ]);
                }
            }

            // 6. Filename validation (prevent special characters)
            if (preg_match('/[<>:"|?*\x00-\x1F]/', $filename)) {
                throw new ScormParsingException(
                    "Security violation: Invalid characters in filename: {$filename}"
                );
            }
        }

        $this->logger->info('ZIP security validation passed', [
            'files' => $numFiles,
            'total_size_mb' => round($totalUncompressedSize / 1024 / 1024, 2),
        ]);

        return [
            'total_size' => $totalUncompressedSize,
            'file_count' => $numFiles,
        ];
    }


    /**
     * Memory-safe streaming extraction for packages >= 200MB
     *
     * Pros: Controlled memory usage, detailed progress tracking
     * Cons: Slower than native extraction
     * Best for: Large SCORM packages (200-800MB)
     *
     * @throws ScormParsingException
     */
    private function extractUsingStreamingMethod(
        \ZipArchive $zip,
        string $extractPath,
        int $numFiles,
        ?callable $progressCallback
    ): string {
        $extractedFiles = 0;
        $this->logger->info("Using streaming extraction (memory-safe mode)", ['files' => $numFiles]);

        for ($i = 0; $i < $numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename === false) {
                continue;
            }

            // Skip directories
            if (substr($filename, -1) === '/') {
                continue;
            }

            // Extract single file with streaming
            $this->extractSingleFileStreaming($zip, $i, $extractPath, $filename);
            $extractedFiles++;

            // Progress updates every 25 files or 10% intervals
            if (($extractedFiles % self::PROGRESS_INTERVAL_FILES === 0) ||
                ($i % max(1, intval($numFiles * 0.1)) === 0)) {
                $progress = $this->calculateExtractionProgress($extractedFiles, $numFiles);
                if ($progressCallback && $progress) {
                    call_user_func($progressCallback, 'extracting', $progress);
                }
            }

            // Memory management every 100 files
            if ($i % 100 === 0) {
                $memoryUsage = memory_get_usage(true);

                if ($memoryUsage > $this->maxMemoryUsage) {
                    $this->logger->warning('High memory usage during extraction', [
                        'memory_mb' => round($memoryUsage / 1024 / 1024, 2),
                        'files_processed' => $extractedFiles,
                    ]);
                    gc_collect_cycles();
                }
            }
        }

        $this->logger->info('Streaming extraction completed', [
            'files_extracted' => $extractedFiles,
            'memory_peak' => memory_get_peak_usage(true),
        ]);

        return $extractPath;
    }

    /**
     * Extract single file from ZIP using stream (memory-efficient)
     *
     * Improved with try-finally for guaranteed resource cleanup
     *
     * @throws ScormParsingException
     */
    private function extractSingleFileStreaming(\ZipArchive $zip, int $index, string $basePath, string $filename): void
    {
        // Defense in depth: re-validate filename (additional security layer)
        if (str_contains($filename, '..')) {
            throw new ScormParsingException("Invalid filename: {$filename}");
        }

        $fullPath = $basePath . DIRECTORY_SEPARATOR . $filename;
        $directory = dirname($fullPath);

        // Create directory structure if needed
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new ScormParsingException("Failed to create directory: {$directory}");
        }

        $fileStream = null;
        $outputHandle = null;

        try {
            // Open stream from ZIP
            $fileStream = $zip->getStream($filename);
            if ($fileStream === false) {
                throw new ScormParsingException("Failed to get stream for: {$filename}");
            }

            // Open output file
            $outputHandle = fopen($fullPath, 'wb');
            if ($outputHandle === false) {
                throw new ScormParsingException("Failed to create file: {$fullPath}");
            }

            // Copy in chunks with validation
            while (!feof($fileStream)) {
                $chunk = fread($fileStream, self::CHUNK_SIZE);

                if ($chunk === false) {
                    throw new ScormParsingException("Failed to read from: {$filename}");
                }

                if ($chunk === '') {
                    break;
                }

                $written = fwrite($outputHandle, $chunk);
                if ($written === false) {
                    throw new ScormParsingException("Failed to write to: {$fullPath}");
                }
            }

        } finally {
            // Guaranteed resource cleanup (even on exceptions)
            if ($fileStream !== null && is_resource($fileStream)) {
                fclose($fileStream);
            }
            if ($outputHandle !== null && is_resource($outputHandle)) {
                fclose($outputHandle);
            }
        }
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
                    $this->uploadSingleFile($filesystem, $file, $extractPath, $packagePath);
                    $fileCount++;

                    // Send progress updates based on configured intervals
                    if (($fileCount % self::PROGRESS_INTERVAL_FILES === 0) || ($fileCount % max(1, intval($totalFiles * 0.1)) === 0)) {
                        $progress = $this->calculateUploadProgress($fileCount, $totalFiles);
                        if ($progressCallback && $progress) {
                            call_user_func($progressCallback, 'uploading', $progress);
                        }
                    }

                    // Monitor memory at configured intervals
                    if ($fileCount % self::MEMORY_CHECK_INTERVAL_UPLOAD === 0) {
                        if (memory_get_usage(true) > $this->maxMemoryUsage) {
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
     * Upload single file to storage using temp file strategy (universal, memory-safe)
     *
     * This simplified strategy works for all file sizes and filesystems:
     * - Memory usage: Constant ~8-16MB regardless of file size
     * - Compatibility: Works with all filesystem adapters (S3, Local, FTP)
     * - Reliability: Guaranteed resource cleanup via try-finally
     *
     * @param mixed $filesystem Flysystem filesystem instance
     * @param \SplFileInfo $file Source file to upload
     * @param string $basePath Base extraction path
     * @param string $storagePath Target storage path
     * @throws ScormParsingException on upload failure
     */
    private function uploadSingleFile($filesystem, \SplFileInfo $file, string $basePath, string $storagePath): void
    {
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
        $storageKey = $storagePath . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        // Create temp file for streaming upload
        $tempFile = tempnam(sys_get_temp_dir(), 'scorm_upload_');
        if ($tempFile === false) {
            throw new ScormParsingException("Failed to create temp file for upload");
        }

        $inputHandle = null;
        $tempHandle = null;

        try {
            // Open source file
            $inputHandle = fopen($file->getPathname(), 'rb');
            if ($inputHandle === false) {
                throw new ScormParsingException("Failed to read file: " . $file->getPathname());
            }

            // Open temp file for writing
            $tempHandle = fopen($tempFile, 'w+b');
            if ($tempHandle === false) {
                throw new ScormParsingException("Failed to open temp file for writing");
            }

            // Copy source → temp file in chunks (memory-safe)
            while (!feof($inputHandle)) {
                $chunk = fread($inputHandle, self::CHUNK_SIZE);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                $written = fwrite($tempHandle, $chunk);
                if ($written === false) {
                    throw new ScormParsingException("Failed to write to temp file");
                }
            }

            // Rewind temp file and upload to storage
            rewind($tempHandle);
            $filesystem->writeStream($storageKey, $tempHandle);

        } finally {
            // Guaranteed resource cleanup (even on exceptions)
            if ($inputHandle !== null && is_resource($inputHandle)) {
                fclose($inputHandle);
            }
            if ($tempHandle !== null && is_resource($tempHandle)) {
                fclose($tempHandle);
            }
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

        if ($currentMemory > $this->maxMemoryUsage * self::MEMORY_WARNING_THRESHOLD) {
            $this->logger->warning('High memory usage detected', [
                'context' => $context,
                'memory_usage' => $currentMemory,
                'memory_limit' => $this->maxMemoryUsage,
                'threshold' => self::MEMORY_WARNING_THRESHOLD,
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
