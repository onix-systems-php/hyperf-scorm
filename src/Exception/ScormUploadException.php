<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Exception;

use Hyperf\Server\Exception\ServerException;
use Throwable;

/**
 * Enhanced SCORM upload exception with detailed error context
 */
class ScormUploadException extends ServerException
{
    private array $context = [];
    private string $errorCode;
    private int $httpStatusCode;

    public function __construct(
        string $message,
        string $errorCode = 'SCORM_UPLOAD_ERROR',
        int $httpStatusCode = 500,
        array $context = [],
        Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
        
        $this->errorCode = $errorCode;
        $this->httpStatusCode = $httpStatusCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
            'timestamp' => time(),
            'trace_id' => $this->context['trace_id'] ?? null,
        ];
    }

    /**
     * Factory methods for common error types
     */
    public static function fileSizeExceeded(int $actualSize, int $maxSize, array $context = []): self
    {
        return new self(
            sprintf('File size %s exceeds maximum allowed size of %s', 
                self::formatBytes($actualSize), 
                self::formatBytes($maxSize)
            ),
            'FILE_SIZE_EXCEEDED',
            413,
            array_merge($context, [
                'actual_size' => $actualSize,
                'max_size' => $maxSize,
                'size_exceeded_by' => $actualSize - $maxSize,
            ])
        );
    }

    public static function invalidFileType(string $actualType, array $allowedTypes, array $context = []): self
    {
        return new self(
            sprintf('Invalid file type "%s". Allowed types: %s', 
                $actualType, 
                implode(', ', $allowedTypes)
            ),
            'INVALID_FILE_TYPE',
            400,
            array_merge($context, [
                'actual_type' => $actualType,
                'allowed_types' => $allowedTypes,
            ])
        );
    }

    public static function rateLimitExceeded(int $limit, string $window, array $context = []): self
    {
        return new self(
            sprintf('Rate limit exceeded. Maximum %d uploads allowed per %s', $limit, $window),
            'RATE_LIMIT_EXCEEDED',
            429,
            array_merge($context, [
                'limit' => $limit,
                'window' => $window,
            ])
        );
    }

    public static function memoryLimitExceeded(int $currentMemoryMB, int $limitMB, array $context = []): self
    {
        return new self(
            sprintf('Memory limit exceeded. Current usage: %dMB, Limit: %dMB', 
                $currentMemoryMB, 
                $limitMB
            ),
            'MEMORY_LIMIT_EXCEEDED',
            507,
            array_merge($context, [
                'current_memory_mb' => $currentMemoryMB,
                'limit_mb' => $limitMB,
            ])
        );
    }

    public static function duplicateFile(string $fileHash, array $context = []): self
    {
        return new self(
            'This file has already been processed recently',
            'DUPLICATE_FILE',
            409,
            array_merge($context, [
                'file_hash' => $fileHash,
            ])
        );
    }

    public static function jobNotFound(string $jobId, array $context = []): self
    {
        return new self(
            sprintf('Job with ID "%s" not found', $jobId),
            'JOB_NOT_FOUND',
            404,
            array_merge($context, [
                'job_id' => $jobId,
            ])
        );
    }


    public static function processingFailed(string $reason, array $context = []): self
    {
        return new self(
            sprintf('SCORM processing failed: %s', $reason),
            'PROCESSING_FAILED',
            500,
            array_merge($context, [
                'failure_reason' => $reason,
            ])
        );
    }

    public static function validationFailed(array $validationErrors, array $context = []): self
    {
        return new self(
            'Validation failed: ' . implode(', ', $validationErrors),
            'VALIDATION_FAILED',
            422,
            array_merge($context, [
                'validation_errors' => $validationErrors,
            ])
        );
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 1) . ' ' . $units[$pow];
    }
}