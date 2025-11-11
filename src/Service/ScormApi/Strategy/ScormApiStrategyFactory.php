<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi\Strategy;

use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

class ScormApiStrategyFactory
{
    public function createForVersion(string $version): ScormApiStrategyInterface
    {
        $enum = ScormVersionEnum::fromString($version);

        $strategyClass = match ($enum) {
            ScormVersionEnum::SCORM_12 => Scorm2004ApiStrategy::class,
            ScormVersionEnum::SCORM_2004 => Scorm2004ApiStrategy::class,
        };

        return new $strategyClass();
    }
}
