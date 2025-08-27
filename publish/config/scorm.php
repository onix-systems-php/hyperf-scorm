<?php

declare(strict_types=1);

/**
 * Configuration for SCORM package.
 */

return [
    // Player Configuration
    'player' => [
        'timeout' => env('SCORM_PLAYER_TIMEOUT', 30000),
        'debug' => env('SCORM_PLAYER_DEBUG', false),
        'api_endpoint' => env('SCORM_API_ENDPOINT', '/api/v1/scorm/api'),
    ],

    // Tracking Configuration
    'tracking' => [
        'auto_commit_interval' => env('SCORM_AUTO_COMMIT_INTERVAL', 30), // seconds
        'enable_debug_logging' => env('SCORM_DEBUG_LOGGING', false),
    ],

    // File Storage Configuration
    'storage' => [
        'disk' => env('SCORM_STORAGE_DISK', 'local'),
        'path' => env('SCORM_STORAGE_PATH', 'scorm-packages'),
        'temp_path' => env('SCORM_TEMP_PATH', 'temp/scorm'),
        'max_file_size' => env('SCORM_MAX_FILE_SIZE', 100 * 1024 * 1024), // 100MB
    ],

    // View Configuration
    'view' => [
        'namespace' => 'OnixSystemsPHP\\HyperfScorm',
        'path' => BASE_PATH . '/vendor/onix-systems-php/hyperf-scorm/storage/view',
        'cache' => env('SCORM_VIEW_CACHE', true),
    ],

    // Security Configuration
    'security' => [
        'allowed_domains' => env('SCORM_ALLOWED_DOMAINS', '*'),
        'iframe_sandbox' => env('SCORM_IFRAME_SANDBOX', 'allow-scripts allow-same-origin allow-forms'),
        'csrf_protection' => env('SCORM_CSRF_PROTECTION', true),
    ],

    // API Configuration
    'api' => [
        'version' => '1.2', // Default SCORM version
        'strict_mode' => env('SCORM_STRICT_MODE', false),
        'error_reporting' => env('SCORM_ERROR_REPORTING', true),
    ],

    // Cache Configuration
    'cache' => [
        'ttl' => env('SCORM_CACHE_TTL', 3600), // 1 hour
        'key_prefix' => env('SCORM_CACHE_PREFIX', 'scorm:'),
    ],
];
