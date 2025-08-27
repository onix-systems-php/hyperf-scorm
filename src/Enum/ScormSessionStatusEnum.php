<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Enum;

/**
 * SCORM Session Status Enum
 */
enum ScormSessionStatusEnum: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case TERMINATED = 'terminated';

    /**
     * Get all valid status values
     */
    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active Session',
            self::SUSPENDED => 'Suspended Session',
            self::COMPLETED => 'Completed Session',
            self::TERMINATED => 'Terminated Session',
        };
    }

    /**
     * Check if status allows resume
     */
    public function canResume(): bool
    {
        return match ($this) {
            self::SUSPENDED, self::ACTIVE => true,
            self::COMPLETED, self::TERMINATED => false,
        };
    }

    /**
     * Check if status is terminal (cannot change)
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::TERMINATED => true,
            self::ACTIVE, self::SUSPENDED => false,
        };
    }
}
