<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Cast;

use Hyperf\Contract\CastsAttributes;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

/**
 * Cast for ScormVersionEnum to handle database serialization/deserialization
 */
class ScormVersionEnumCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?ScormVersionEnum
    {
        if ($value === null) {
            return null;
        }

        return ScormVersionEnum::fromString((string)$value);
    }

    /**
     * Prepare the given value for storage.
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ScormVersionEnum) {
            return $value->value;
        }

        // If it's a string, try to convert it to enum and back
        if (is_string($value)) {
            return ScormVersionEnum::fromString($value)->value;
        }

        throw new \InvalidArgumentException(
            'Value must be ScormVersionEnum instance or valid version string'
        );
    }
}

