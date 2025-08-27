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
                // Repository Interfaces -> Implementations
                \OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepositoryInterface::class =>
                    \OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository::class,

                \OnixSystemsPHP\HyperfScorm\Repository\ScormAttemptRepositoryInterface::class =>
                    \OnixSystemsPHP\HyperfScorm\Repository\ScormAttemptRepository::class,

                \OnixSystemsPHP\HyperfScorm\Repository\ScormScoRepositoryInterface::class =>
                    \OnixSystemsPHP\HyperfScorm\Repository\ScormScoRepository::class,

                // Service Classes
                \OnixSystemsPHP\HyperfScorm\Service\ScormPackageService::class =>
                    \OnixSystemsPHP\HyperfScorm\Service\ScormPackageService::class,

                \OnixSystemsPHP\HyperfScorm\Service\ScormAttemptService::class =>
                    \OnixSystemsPHP\HyperfScorm\Service\ScormAttemptService::class,

                \OnixSystemsPHP\HyperfScorm\Service\ScormScoService::class =>
                    \OnixSystemsPHP\HyperfScorm\Service\ScormScoService::class,

                \OnixSystemsPHP\HyperfScorm\Service\ScormPlayerService::class =>
                    \OnixSystemsPHP\HyperfScorm\Service\ScormPlayerService::class,

                // Legacy interfaces (for backward compatibility)
                \OnixSystemsPHP\HyperfScorm\Service\ScormTrackingServiceInterface::class =>
                    \OnixSystemsPHP\HyperfScorm\Service\ScormTrackingService::class,
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
                    'description' => 'The database migrations for SCORM package.',
                    'source' => __DIR__ . '/../publish/migrations',
                    'destination' => BASE_PATH . '/migrations',
                ],
                [
                    'id' => 'scorm_public_data',
                    'description' => 'Move public data',
                    'source' => __DIR__ . '../public/',
                    'destination' => BASE_PATH . '/public/scorm/',
                ],
            ],
        ];
    }
}
