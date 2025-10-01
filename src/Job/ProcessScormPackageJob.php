<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Job;

use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Upload\UploadedFile;
use Hyperf\Redis\Redis;
use OnixSystemsPHP\HyperfScorm\Service\ScormFileProcessor;
use OnixSystemsPHP\HyperfScorm\Service\ScormWebSocketNotificationService;
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
        $wsService = $container->get(ScormWebSocketNotificationService::class);
        
        $progressKey = self::PROGRESS_KEY_PREFIX . $this->jobId;
        $resultKey = self::RESULT_KEY_PREFIX . $this->jobId;
        
        try {
            
            // Update progress - starting (send WebSocket notification)
            $progressData = [
                'status' => 'processing',
                'progress' => 0,
                'stage' => 'initializing',
                'stage_details' => 'Preparing SCORM package for processing...',
                'started_at' => time(),
                'file_size' => $this->fileSize,
                'memory_usage' => memory_get_usage(true),
                'processed_bytes' => 0,
            ];
            $this->setRedisWithTtl($redis, $progressKey, $progressData, 3600);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $progressData);
            
            // Create UploadedFile from temp path
            $uploadedFile = $this->createUploadedFileFromPath();
            
            // Update progress - extracting (send WebSocket notification)
            $extractingData = [
                'status' => 'processing',
                'progress' => 10,
                'stage' => 'extracting',
                'stage_details' => 'Extracting files from SCORM package...',
                'memory_usage' => memory_get_usage(true),
                'file_size' => $this->fileSize,
                'processed_bytes' => (int)($this->fileSize * 0.1),
            ];
            $this->setRedisWithTtl($redis, $progressKey, $extractingData, 3600);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $extractingData);
            
            // Process the SCORM package with progress callback
            $progressCallback = function(string $stage, array $progressData) use ($redis, $wsService, $progressKey) {
                $this->handleProgressCallback($stage, $progressData, $redis, $wsService, $progressKey);
            };
            
            $processedPackage = $processor->run($uploadedFile, $progressCallback);
            
            // Update progress - processing completed (send WebSocket notification)
            $processingData = [
                'status' => 'processing',
                'progress' => 60,
                'stage' => 'processing',
                'stage_details' => 'SCORM manifest processed successfully',
                'memory_usage' => memory_get_usage(true),
                'file_size' => $this->fileSize,
                'processed_bytes' => (int)($this->fileSize * 0.6),
            ];
            $this->setRedisWithTtl($redis, $progressKey, $processingData, 3600);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $processingData);
            
            // Update progress - uploading to storage (send WebSocket notification)
            $uploadingData = [
                'status' => 'processing',
                'progress' => 80,
                'stage' => 'uploading',
                'stage_details' => 'Uploading content to storage...',
                'file_size' => $this->fileSize,
                'memory_usage' => memory_get_usage(true),
                'processed_bytes' => (int)($this->fileSize * 0.8),
            ];
            $this->setRedisWithTtl($redis, $progressKey, $uploadingData, 3600);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $uploadingData);
            
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
            
            // Update progress - completed (send WebSocket notification)
            $completedData = [
                'status' => 'completed',
                'progress' => 100,
                'stage' => 'completed',
                'stage_details' => 'SCORM package processing completed successfully',
                'completed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
                'file_size' => $this->fileSize,
                'processed_bytes' => $this->fileSize,
            ];
            $this->setRedisWithTtl($redis, $progressKey, $completedData, 3600);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $completedData);
            
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
            
            // Update progress - failed (send WebSocket notification)
            $failedData = [
                'status' => 'failed',
                'progress' => 0,
                'stage' => 'failed',
                'stage_details' => 'Processing failed: ' . substr($e->getMessage(), 0, 100),
                'error' => $e->getMessage(),
                'failed_at' => time(),
                'memory_peak' => memory_get_peak_usage(true),
                'file_size' => $this->fileSize,
            ];
            $this->setRedisWithTtl($redis, $progressKey, $failedData, 3600);
            $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $failedData);
            
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

    /**
     * Handle progress callback from ScormFileProcessor
     */
    private function handleProgressCallback(string $stage, array $progressData, $redis, $wsService, string $progressKey): void
    {
        $fullProgressData = array_merge($progressData, [
            'status' => 'processing',
            'stage' => $stage,
            'file_size' => $this->fileSize,
        ]);

        $this->setRedisWithTtl($redis, $progressKey, $fullProgressData, 3600);
        $wsService->sendUploadProgressUpdate($this->userId, $this->jobId, $fullProgressData);
    }
}