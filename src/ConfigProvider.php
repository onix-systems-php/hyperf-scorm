<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
            ],
            'commands' => [],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'view' => [
                'namespaces' => [
                    'OnixSystemsPHP\HyperfScorm' => __DIR__ . '/../storage/view',
                    'scorm_public' => __DIR__ . '/../storage/public',
                ],
            ],
            'process' => [
                \Hyperf\AsyncQueue\Process\ConsumerProcess::class,
                \OnixSystemsPHP\HyperfScorm\Process\UploadQueueConsumer::class ,
            ],
            'file' => [
                'storage'=> [
                    'temp-queue' => [
                        'driver' => \Hyperf\Filesystem\Adapter\LocalAdapterFactory::class,
                        'root' => BASE_PATH . '/runtime/scorm-queue-tmp',
                    ]
                ],
            ],
            'async_queue' => [
                'scorm-processing' => [
                    'driver' => \Hyperf\AsyncQueue\Driver\RedisDriver::class,
                    'redis' => [
                        'pool' => 'default',
                    ],
                    'channel' => 'scorm-jobs',
                    'timeout' => 2,
                    'retry_seconds' => 10,
                    'handle_timeout' => 1800, // 30 minutes for large SCORM files
                    'processes' => 2,
                    'concurrent' => [
                        'limit' => 2,
                    ],
                    'max_attempts' => 3,
                ],
            ],
            'publish' => [
                [
                    'id' => 'scorm_config',
                    'description' => 'The config for onix-systems-php/hyperf-scorm.',
                    'source' => __DIR__ . '/../publish/config/scorm.php',
                    'destination' => BASE_PATH . '/config/autoload/scorm.php',
                ],
                [
                    'id' => 'scorm_migrations',
                    'description' => 'The database migrations for onix-systems-php/hyperf-scorm.',
                    'source' => __DIR__ . '/../publish/migrations/2025_01_31_000001_create_scorm_packages_table.php',
                    'destination' => BASE_PATH . '/migrations/2025_01_31_000001_create_scorm_packages_table.php',
                ],
                [
                    'id' => 'scorm_example',
                    'description' => 'Move example SCORM files to public directory.',
                    'source' => __DIR__ . '/../publish/public/example',
                    'destination' => BASE_PATH . '/storage/public/assets/scorm/',
                ],
            ],
        ];
    }
}
