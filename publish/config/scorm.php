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
        'auto_commit_interval' => env('SCORM_AUTO_COMMIT_INTERVAL', 30), // seconds
    ],

    'cache' => [
        'ttl' => env('SCORM_CACHE_TTL', 3600), // 1 hour
    ],

    'ws' => [
        'name' => env('SCORM_WS_NAME', 'socket-io'),
    ],

    'redis' => [
        'ttl' => [
            'job_status' => (int) env('SCORM_REDIS_TTL_JOB_STATUS', 3600), // 1 hour
            'job_result' => (int) env('SCORM_REDIS_TTL_JOB_RESULT', 86400), // 24 hours
            'websocket' => (int) env('SCORM_REDIS_TTL_WEBSOCKET', 86400), // 24 hours
        ],
    ],
];
