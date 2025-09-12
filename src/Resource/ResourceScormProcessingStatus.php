<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ResourceScormProcessingStatus',
    properties: [
        new OA\Property(
            property: 'job_id',
            type: 'string',
            description: 'Unique job identifier',
            example: 'scorm_68c1a39d180a9_1757520797'
        ),
        new OA\Property(
            property: 'progress',
            type: 'object',
            description: 'Current processing progress',
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['processing', 'completed', 'failed', 'cancelled']),
                new OA\Property(property: 'progress', type: 'integer', minimum: 0, maximum: 100),
                new OA\Property(property: 'stage', type: 'string', enum: ['initializing', 'extracting', 'uploading', 'completed', 'error', 'cancelled']),
                new OA\Property(property: 'started_at', type: 'integer', description: 'Timestamp when job started'),
                new OA\Property(property: 'completed_at', type: 'integer', description: 'Timestamp when job completed'),
                new OA\Property(property: 'failed_at', type: 'integer', description: 'Timestamp when job failed'),
                new OA\Property(property: 'memory_usage', type: 'integer', description: 'Current memory usage in bytes'),
                new OA\Property(property: 'memory_peak', type: 'integer', description: 'Peak memory usage in bytes'),
                new OA\Property(property: 'error', type: 'string', description: 'Error message if failed'),
            ],
            nullable: true
        ),
        new OA\Property(
            property: 'result',
            type: 'object',
            description: 'Processing result data',
            properties: [
                new OA\Property(property: 'status', type: 'string', enum: ['completed', 'failed', 'permanently_failed']),
                new OA\Property(
                    property: 'manifest_data',
                    type: 'object',
                    description: 'SCORM manifest information',
                    properties: [
                        new OA\Property(property: 'identifier', type: 'string'),
                        new OA\Property(property: 'title', type: 'string'),
                        new OA\Property(property: 'version', type: 'string'),
                        new OA\Property(property: 'launch_url', type: 'string'),
                    ]
                ),
                new OA\Property(property: 'content_path', type: 'string', description: 'Storage path for SCORM content'),
                new OA\Property(property: 'user_id', type: 'integer', description: 'ID of user who uploaded'),
                new OA\Property(property: 'original_filename', type: 'string', description: 'Original uploaded filename'),
                new OA\Property(property: 'file_size', type: 'integer', description: 'File size in bytes'),
                new OA\Property(property: 'processed_at', type: 'integer', description: 'Timestamp when processing completed'),
                new OA\Property(property: 'memory_peak', type: 'integer', description: 'Peak memory usage during processing'),
                new OA\Property(property: 'error', type: 'string', description: 'Error message if failed'),
            ],
            nullable: true
        ),
    ]
)]
class ResourceScormProcessingStatus extends AbstractResource
{
    /**
     * @method __construct(array $resource)
     * @property array $resource
     */
    
    public function toArray(): array
    {
        return [
            'job_id' => $this->resource['job_id'] ?? null,
            'progress' => $this->formatProgress($this->resource['progress'] ?? null),
            'result' => $this->formatResult($this->resource['result'] ?? null),
        ];
    }
    
    /**
     * Format progress data
     */
    private function formatProgress(?array $progress): ?array
    {
        if (!$progress) {
            return null;
        }
        
        return [
            'status' => $progress['status'] ?? 'unknown',
            'progress' => (int)($progress['progress'] ?? 0),
            'stage' => $progress['stage'] ?? 'unknown',
            'started_at' => isset($progress['started_at']) ? (int)$progress['started_at'] : null,
            'completed_at' => isset($progress['completed_at']) ? (int)$progress['completed_at'] : null,
            'failed_at' => isset($progress['failed_at']) ? (int)$progress['failed_at'] : null,
            'cancelled_at' => isset($progress['cancelled_at']) ? (int)$progress['cancelled_at'] : null,
            'memory_usage' => isset($progress['memory_usage']) ? (int)$progress['memory_usage'] : null,
            'memory_peak' => isset($progress['memory_peak']) ? (int)$progress['memory_peak'] : null,
            'error' => $progress['error'] ?? null,
        ];
    }
    
    /**
     * Format result data
     */
    private function formatResult(?array $result): ?array
    {
        if (!$result) {
            return null;
        }
        
        return [
            'status' => $result['status'] ?? 'unknown',
            'manifest_data' => $this->formatManifestData($result['manifest_data'] ?? null),
            'content_path' => $result['content_path'] ?? null,
            'user_id' => isset($result['user_id']) ? (int)$result['user_id'] : null,
            'original_filename' => $result['original_filename'] ?? null,
            'file_size' => isset($result['file_size']) ? (int)$result['file_size'] : null,
            'metadata' => $result['metadata'] ?? [],
            'processed_at' => isset($result['processed_at']) ? (int)$result['processed_at'] : null,
            'failed_at' => isset($result['failed_at']) ? (int)$result['failed_at'] : null,
            'memory_peak' => isset($result['memory_peak']) ? (int)$result['memory_peak'] : null,
            'error' => $result['error'] ?? null,
            'attempts' => isset($result['attempts']) ? (int)$result['attempts'] : null,
        ];
    }
    
    /**
     * Format manifest data
     */
    private function formatManifestData(?array $manifestData): ?array
    {
        if (!$manifestData) {
            return null;
        }
        
        return [
            'identifier' => $manifestData['identifier'] ?? null,
            'title' => $manifestData['title'] ?? null,
            'version' => $manifestData['version'] ?? null,
            'launch_url' => $manifestData['launch_url'] ?? null,
            'description' => $manifestData['description'] ?? null,
            'scos' => $manifestData['scos'] ?? [],
            'metadata' => $manifestData['metadata'] ?? [],
        ];
    }
    
    /**
     * Check if processing is completed successfully
     */
    public function isCompleted(): bool
    {
        $progress = $this->resource['progress'] ?? null;
        return $progress && ($progress['status'] ?? '') === 'completed';
    }
    
    /**
     * Check if processing failed
     */
    public function isFailed(): bool
    {
        $progress = $this->resource['progress'] ?? null;
        return $progress && in_array($progress['status'] ?? '', ['failed', 'permanently_failed']);
    }
    
    /**
     * Check if processing is still in progress
     */
    public function isProcessing(): bool
    {
        $progress = $this->resource['progress'] ?? null;
        return $progress && ($progress['status'] ?? '') === 'processing';
    }
    
    /**
     * Check if processing was cancelled
     */
    public function isCancelled(): bool
    {
        $progress = $this->resource['progress'] ?? null;
        return $progress && ($progress['status'] ?? '') === 'cancelled';
    }
    
    /**
     * Get current progress percentage
     */
    public function getProgressPercentage(): int
    {
        $progress = $this->resource['progress'] ?? null;
        return (int)($progress['progress'] ?? 0);
    }
    
    /**
     * Get current processing stage
     */
    public function getCurrentStage(): string
    {
        $progress = $this->resource['progress'] ?? null;
        return $progress['stage'] ?? 'unknown';
    }
    
    /**
     * Get error message if failed
     */
    public function getErrorMessage(): ?string
    {
        $progress = $this->resource['progress'] ?? null;
        $result = $this->resource['result'] ?? null;
        
        return $progress['error'] ?? $result['error'] ?? null;
    }
}