<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Factory;

use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\Strategy\Scorm2004ApiStrategy;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\Strategy\ScormApiStrategyInterface;

/**
 * Factory for creating SCORM API strategies based on version.
 */
class ScormApiStrategyFactory
{
    /**
     * Create appropriate strategy for SCORM version.
     */
    public function createForVersion(string $version): ScormApiStrategyInterface
    {
        $enum = ScormVersionEnum::fromString($version);

        $strategyClass = match ($enum) {
            ScormVersionEnum::SCORM_12 => Scorm2004ApiStrategy::class,
            ScormVersionEnum::SCORM_2004 => Scorm2004ApiStrategy::class,
        };

        return new $strategyClass();
    }

    /**
     * Get all supported versions.
     */
    public function getSupportedVersions(): array
    {
        return ScormVersionEnum::values();
    }

    /**
     * Check if version is supported.
     */
    public function isVersionSupported(string $version): bool
    {
        try {
            ScormVersionEnum::fromString($version);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
