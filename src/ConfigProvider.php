<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm;

use OnixSystemsPHP\HyperfScorm\Contract\Gateway\ScormGatewayInterface;
use OnixSystemsPHP\HyperfScorm\Gateway\ScormGateway;
use function Hyperf\Support\env;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                ScormGatewayInterface::class => ScormGateway::class,
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
                'storage' => [
                    'scormS3' => [
                        'driver' => \Hyperf\Filesystem\Adapter\S3AdapterFactory::class,
                        'credentials' => [
                            'key' => env('SCORM_S3_KEY'),
                            'secret' => env('SCORM_S3_SECRET'),
                        ],
                        'region' => env('SCORM_S3_REGION'),
                        'version' => 'latest',
                        'bucket_endpoint' => false,
                        'use_path_style_endpoint' => env('SCORM_S3_PATH_STYLE', 'no') === 'yes',
                        'endpoint' => env('SCORM_S3_ENDPOINT'),
                        'bucket_name' => env('SCORM_S3_BUCKET'),
                        'domain' => env('SCORM_S3_DOMAIN'),
                    ],
                    'temp-queue' => [
                        'driver' => \Hyperf\Filesystem\Adapter\LocalAdapterFactory::class,
                        'root' => BASE_PATH . '/runtime/scorm-queue-tmp',
                    ],
                ],

            ],
            'async_queue' => [
                'scorm-processing' => [
                    'driver' => \Hyperf\AsyncQueue\Driver\RedisDriver::class,
                    'redis' => [
                        'pool' => 'default',
                    ],
                    'channel' => 'scorm-jobs',
                    'timeout' => 3,
                    'retry_seconds' => 10,
                    'handle_timeout' => 1800,
                    'processes' => 3,
                    'concurrent' => [
                        'limit' => 3,
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
                    'id' => 'scorm_controller',
                    'description' => 'Scorm controller for onix-systems-php/hyperf-scorm.',
                    'source' => __DIR__ . '/../publish/controller/ScormController.php',
                    'destination' => BASE_PATH . '/app/Scorm/Controller/ScormController.php',
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
