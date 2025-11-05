<?php
declare(strict_types=1);

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
                    'OnixSystemsPHP\\HyperfScorm' => __DIR__ . '/../storage/view',
                    'scorm_public' => __DIR__ . '/../storage/public',
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
