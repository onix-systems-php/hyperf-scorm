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
        'default' => env('SCORM_STORAGE_DRIVER', 'scormS3'),
        'local' => [
            'domain' => env('DOMAIN_API'),
            'public_path_prefix' => DIRECTORY_SEPARATOR . 'scorm-packages',
            'storage_path_prefix' => DIRECTORY_SEPARATOR . 'scorm-packages',
        ],
        'scormS3' => [
            'domain' => env('SCORM_S3_DOMAIN'),
            'public_path_prefix' => DIRECTORY_SEPARATOR .  'scorm-packages',
            'storage_path_prefix' => DIRECTORY_SEPARATOR . 'scorm-packages',
        ],
    ],

    'upload' => [
        'max_file_size' => env('SCORM_MAX_FILE_SIZE', 100) * 1024 * 1024, // 100MB
    ],

    'player' => [
        'timeout' => env('SCORM_API_TIMEOUT', 30000), // 30 seconds in milliseconds
        'debug' => env('SCORM_DEBUG', false),
    ],

    'tracking' => [
        'auto_commit_interval' => env('SCORM_AUTO_COMMIT_INTERVAL', 30) * 1000, // seconds
    ],

    'cache' => [
        'ttl' => env('SCORM_CACHE_TTL', 3600), // 1 hour
    ],

    'ws' => [
        'name' => env('SCORM_WS_NAME', 'socket-io'),
    ],

    'redis' => [
        'ttl' => [
            'job_status' => (int)env('SCORM_REDIS_TTL_JOB_STATUS', 3600), // 1 hour
            'job_result' => (int)env('SCORM_REDIS_TTL_JOB_RESULT', 86400), // 24 hours
            'websocket' => (int)env('SCORM_REDIS_TTL_WEBSOCKET', 86400), // 24 hours
        ],
    ],
];
