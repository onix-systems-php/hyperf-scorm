<?php

declare(strict_types=1);

return [
    'storage' => [
        'default' => env('SCORM_STORAGE_DRIVER', 's3'),
        'base_path' => env('SCORM_STORAGE_BASE_PATH', 'scorm-packages'),

        's3' => [
            'public_url' => env('SCORM_S3_PUBLIC_URL', env('AWS_URL', 'https://s3.amazonaws.com')),
            'bucket' => env('SCORM_S3_BUCKET', env('AWS_BUCKET', 'scorm-content')),
            'region' => env('SCORM_S3_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        ],

        'local' => [
            'public_url' => env('SCORM_LOCAL_PUBLIC_URL', 'http://localhost:9501/storage'),
            'path' => env('SCORM_LOCAL_PATH', BASE_PATH . '/storage/scorm'),
        ],
    ],

    'upload' => [
        'max_file_size' => env('SCORM_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
        'allowed_extensions' => ['zip'],
        'temp_disk' => env('SCORM_TEMP_DISK', 'scorm_temp'),
    ],

    'manifest' => [
        'required_files' => ['imsmanifest.xml'],
        'max_scos' => env('SCORM_MAX_SCOS', 50),
    ],

    'player' => [
        'api_endpoint' => env('SCORM_API_ENDPOINT', '/api/v1/scorm/api'),
        'timeout' => env('SCORM_API_TIMEOUT', 30000), // 30 seconds in milliseconds
        'debug' => env('SCORM_DEBUG', false),
    ],

    'tracking' => [
        'store_detailed_logs' => env('SCORM_DETAILED_LOGS', true),
        'auto_commit_interval' => env('SCORM_AUTO_COMMIT_INTERVAL', 30), // seconds
        'max_suspend_data_length' => [
            '1.2' => 4096,   // SCORM 1.2 limit
            '2004' => 64000, // SCORM 2004 limit
        ],
    ],

    'cache' => [
        'ttl' => env('SCORM_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('SCORM_CACHE_PREFIX', 'scorm:'),
    ],
];
