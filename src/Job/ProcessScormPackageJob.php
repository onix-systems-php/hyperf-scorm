<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\Redis\Redis;
use OnixSystemsPHP\HyperfScorm\Service\ScormFileProcessor;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Asynchronous job for processing SCORM packages
 * Uses streaming processor to handle large files efficiently
 */
class ProcessScormPackageJob extends Job
{
    public const QUEUE_NAME = 'default';
    public const PROGRESS_KEY_PREFIX = 'scorm_progress:';
    public const RESULT_KEY_PREFIX = 'scorm_result:';
    
    protected int $maxAttempts = 3;
    protected int $delay = 0;
    
    public function __construct(
        private readonly string $jobId,
        private readonly string $tempFilePath,
        private readonly string $originalFilename,
        private readonly int $fileSize,
        private readonly int $userId,
        private readonly array $metadata = []
    ) {
    }

    public function handle(): void
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerInterface::class);
        $processor = $container->get(ScormFileProcessor::class);
        $redis = $container->get(Redis::class);
        
        $progressKey = self::PROGRESS_KEY_PREFIX . $this->jobId;
        $resultKey = self::RESULT_KEY_PREFIX . $this->jobId;
        
        try {
            $logger->info('Starting SCORM package processing job', [
                'job_id' => $this->jobId,
                'filename' => $this->originalFilename,
                'file_size' => $this->fileSize,
                'user_id' => $this->userId,
                'memory_start' => memory_get_usage(true),
            ]);
            
            // Update progress - starting
            $this->setRedisWithTtl($redis, $progressKey, [
                'status' => 'processing',
                'progress' => 0,
                'stage' => 'initializing',
                'started_at' => time(),
                'memory_usage' => memory_get_usage(true),
            ], 3600);
            
            // Create UploadedFile from temp path
            $uploadedFile = $this->createUploadedFileFromPath();
            
            // Update progress - extracting
            $this->setRedisWithTtl($redis, $progressKey, [
                'status' => 'processing',
                'progress' => 25,
                'stage' => 'extracting',
                'memory_usage' => memory_get_usage(true),
            ], 3600);
            
            // Process the SCORM package
            $processedPackage = $processor->run($uploadedFile);
            
            // Update progress - uploading to storage
            $this->setRedisWithTtl($redis, $progressKey, [
                'status' => 'processing',
                'progress' => 75,
                'stage' => 'uploading',
                'memory_usage' => memory_get_usage(true),
            ], 3600);
            
            // Save result
            $resultData = [
                'status' => 'completed',
                'manifest_data' => $processedPackage->manifestData->toArray(),
                'content_path' => $processedPackage->contentPath,
                'user_id' => $this->userId,
                'original_filename' => $this->originalFilename,
                'file_size' => $this->fileSize,
                'metadata' => $this->metadata,
                'processed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
            ];
            
            $redis->setex($resultKey, 86400, json_encode($resultData)); // 24 hours
            
            // Update progress - completed
            $this->setRedisWithTtl($redis, $progressKey, [
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'completed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
            ], 3600);
            
            // Cleanup temp directory
            $processedPackage->cleanup();
            
            $logger->info('SCORM package processing job completed successfully', [
                'job_id' => $this->jobId,
                'content_path' => $processedPackage->contentPath,
                'memory_peak' => memory_get_peak_usage(true),
            ]);
            
        } catch (Throwable $e) {
            $logger->error('SCORM package processing job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'memory_peak' => memory_get_peak_usage(true),
            ]);
            
            // Update progress - failed
            $this->setRedisWithTtl($redis, $progressKey, [
                'status' => 'failed',
                'progress' => 0,
                'stage' => 'error',
                'error' => $e->getMessage(),
                'failed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
            ], 3600);
            
            // Save error result
            $this->setRedisWithTtl($redis, $resultKey, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'user_id' => $this->userId,
                'original_filename' => $this->originalFilename,
                'failed_at' => time(),
            ], 86400);
            
            // Cleanup temp file (local or S3)
            $this->cleanupTempFile($container);
            
            throw $e;
        }
    }

    public function failed(Throwable $throwable): void
    {
        $container = ApplicationContext::getContainer();
        $logger = $container->get(LoggerInterface::class);
        $redis = $container->get(Redis::class);
        
        $progressKey = self::PROGRESS_KEY_PREFIX . $this->jobId;
        $resultKey = self::RESULT_KEY_PREFIX . $this->jobId;
        
        $logger->error('SCORM processing job permanently failed', [
            'job_id' => $this->jobId,
            'attempts' => $this->attempts(),
            'error' => $throwable->getMessage(),
        ]);
        
        // Mark as permanently failed
        $redis->setex($progressKey, 3600, json_encode([
            'status' => 'failed',
            'progress' => 0,
            'stage' => 'permanently_failed',
            'error' => $throwable->getMessage(),
            'attempts' => $this->attempts(),
            'failed_at' => time(),
        ]));
        
        $redis->setex($resultKey, 86400, json_encode([
            'status' => 'permanently_failed',
            'error' => $throwable->getMessage(),
            'attempts' => $this->attempts(),
            'user_id' => $this->userId,
            'original_filename' => $this->originalFilename,
            'failed_at' => time(),
        ]));
        
        // Cleanup temp file (local or S3)
        $this->cleanupTempFile($container);
    }

    /**
     * Create UploadedFile instance from temporary file path
     */
    private function createUploadedFileFromPath(): UploadedFile
    {
        // Since we now use original uploaded file paths, validate existence
        if (!file_exists($this->tempFilePath)) {
            throw new ScormParsingException("Uploaded file not found: {$this->tempFilePath}");
        }
        
        return new UploadedFile(
            $this->tempFilePath,
            $this->fileSize,
            UPLOAD_ERR_OK,
            $this->originalFilename
        );
    }

    /**
     * Remove directory recursively
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
     * Get job identifier
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Get user ID
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Get original filename
     */
    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * Get file size
     */
    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    /**
     * Cleanup temporary uploaded file
     * Note: PHP automatically cleans up uploaded files, but we can explicitly clean up if needed
     */
    private function cleanupTempFile($container): void
    {
        try {
            // Check if the original uploaded file still exists and clean it up
            // Note: PHP usually cleans up temp files automatically after script execution
            if (file_exists($this->tempFilePath) && str_contains($this->tempFilePath, '/tmp/')) {
                unlink($this->tempFilePath);
                $container->get(LoggerInterface::class)->info('Temporary uploaded file cleaned up', [
                    'job_id' => $this->jobId,
                    'temp_path' => $this->tempFilePath,
                ]);
            }
        } catch (Throwable $e) {
            $container->get(LoggerInterface::class)->warning('Failed to cleanup temporary file', [
                'job_id' => $this->jobId,
                'temp_path' => $this->tempFilePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Set Redis value with TTL
     */
    private function setRedisWithTtl($redis, string $key, array $data, int $ttl): void
    {
        $redis->set($key, json_encode($data), 'EX', $ttl);
    }
}