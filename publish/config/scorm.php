<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use function Hyperf\Support\env;

return [
    'storage' => [
        'default' => env('SCORM_STORAGE_DRIVER', 's3'),
        'base_path' => env('SCORM_STORAGE_BASE_PATH', 'scorm-packages'),

        's3' => [
            'public_url' => env('SCORM_S3_PUBLIC_URL', env('AWS_URL')),
            'bucket' => env('SCORM_S3_BUCKET', env('AWS_BUCKET', 'scorm-content')),
            'region' => env('SCORM_S3_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        ],

        'local' => [
            'public_url' => env('SCORM_LOCAL_PUBLIC_URL', 'http://localhost/public'),
            'path' => env('SCORM_LOCAL_PATH', BASE_PATH . '/storage/scorm'),
        ],
    ],

    'upload' => [
        'max_file_size' => env('SCORM_MAX_FILE_SIZE', 100) * 1024 * 1024, // 100MB
    ],

    'player' => [
        'api_endpoint' => env('SCORM_API_ENDPOINT', '/v1/api/scorm'),
        'timeout' => env('SCORM_API_TIMEOUT', 30000), // 30 seconds in milliseconds
        'debug' => env('SCORM_DEBUG', false),
    ],

    'tracking' => [
        'store_detailed_logs' => env('SCORM_DETAILED_LOGS', true),
        'auto_commit_interval' => env('SCORM_AUTO_COMMIT_INTERVAL', 30), // seconds
    ],

    'cache' => [
        'ttl' => env('SCORM_CACHE_TTL', 3600), // 1 hour
    ],

    'performance' => [
        'max_memory_usage' => env('SCORM_MAX_MEMORY_USAGE', 512 * 1024 * 1024), // 512MB
        'memory_warning_threshold' => 0.8, // 80% - trigger warnings
        'memory_temp_file_threshold' => 0.7, // 70% - switch to temp file strategy
        'temp_cleanup_ttl' => env('SCORM_TEMP_CLEANUP_TTL', 86400), // 24 hours
        's3_streaming_enabled' => env('SCORM_S3_STREAMING_ENABLED', true),
        'parallel_processing_limit' => env('SCORM_PARALLEL_LIMIT', 3),
    ],

    'processing' => [
        'async_threshold_bytes' => env('SCORM_ASYNC_THRESHOLD', 25) * 1024 * 1024, // 25MB - files larger than this will be processed asynchronously
        'memory_check_interval_extraction' => 100, // Check memory every N files during extraction
    ],

    'queue' => [
        'max_attempts' => env('SCORM_QUEUE_MAX_ATTEMPTS', 3), // Maximum retry attempts for failed jobs
        'retry_delay' => env('SCORM_QUEUE_RETRY_DELAY', 0), // Delay in seconds between retry attempts
    ],

    'redis' => [
        'ttl' => [
            'job_status' => (int) env('SCORM_REDIS_TTL_JOB_STATUS', 3600), // 1 hour
            'job_result' => (int) env('SCORM_REDIS_TTL_JOB_RESULT', 86400), // 24 hours
            'websocket' => (int) env('SCORM_REDIS_TTL_WEBSOCKET', 86400), // 24 hours
        ],
    ],

    'messages' => [
        'stage_details' => [
            'initializing' => 'Preparing SCORM package...',
            'extracting' => 'Extracting files from package...',
            'processing' => 'Processing SCORM manifest...',
            'uploading' => 'Uploading content to storage...',
            'completed' => 'Package processing completed',
            'failed' => 'Processing failed',
        ],
    ],
];
