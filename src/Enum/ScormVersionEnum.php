<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Enum;

/**
 * SCORM Version Enum with version mapping.
 */
enum ScormVersionEnum: string
{
    case SCORM_12 = '1.2';
    case SCORM_2004 = '2004';

    /**
     * Get normalized version from various input formats.
     */
    public static function fromString(string $version): self
    {
        $versionMappings = [
            '1.2' => self::SCORM_12,
            'scorm_1_2' => self::SCORM_12,
            'scorm1.2' => self::SCORM_12,
            '2004' => self::SCORM_2004,
            'scorm_2004' => self::SCORM_2004,
            'scorm2004' => self::SCORM_2004,
            '2004 3rd edition' => self::SCORM_2004,
            '2004 4th edition' => self::SCORM_2004,
        ];

        $normalizedVersion = strtolower(trim($version));

        return $versionMappings[$normalizedVersion] ?? throw new \InvalidArgumentException(
            "Unsupported SCORM version: {$version}. Supported: " . implode(', ', array_keys($versionMappings))
        );
    }

    /**
     * Get all possible input formats for this version.
     */
    public function getInputFormats(): array
    {
        return match ($this) {
            self::SCORM_12 => ['1.2', 'scorm_1_2', 'scorm1.2'],
            self::SCORM_2004 => ['2004', 'scorm_2004', 'scorm2004', '2004 3rd edition', '2004 4th edition'],
        };
    }

    /**
     * Get human-readable label.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::SCORM_12 => 'SCORM 1.2',
            self::SCORM_2004 => 'SCORM 2004',
        };
    }

    /**
     * Get all valid enum values as strings.
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Get all possible input formats.
     */
    public static function getAllInputFormats(): array
    {
        $formats = [];
        foreach (self::cases() as $case) {
            $formats = array_merge($formats, $case->getInputFormats());
        }
        return $formats;
    }
}
