<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Enum;

/**
 * SCORM Activity Type Enum
 */
enum ScormActivityTypeEnum: string
{
    case QUESTION_ANSWER = 'question_answer';
    case LESSON_COMPLETE = 'lesson_complete';
    case INTERACTION = 'interaction';
    case SCORE_UPDATE = 'score_update';
    case LOCATION_CHANGE = 'location_change';
    case SESSION_START = 'session_start';
    case SESSION_SUSPEND = 'session_suspend';
    case SESSION_TERMINATE = 'session_terminate';

    /**
     * Get all valid activity type values
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
            self::QUESTION_ANSWER => 'Question Answer',
            self::LESSON_COMPLETE => 'Lesson Complete',
            self::INTERACTION => 'User Interaction',
            self::SCORE_UPDATE => 'Score Update',
            self::LOCATION_CHANGE => 'Location Change',
            self::SESSION_START => 'Session Start',
            self::SESSION_SUSPEND => 'Session Suspend',
            self::SESSION_TERMINATE => 'Session Terminate',
        };
    }

    /**
     * Check if activity type is related to progress tracking
     */
    public function isProgressActivity(): bool
    {
        return match ($this) {
            self::LESSON_COMPLETE, self::SCORE_UPDATE, self::LOCATION_CHANGE => true,
            default => false,
        };
    }

    /**
     * Check if activity type is related to session management
     */
    public function isSessionActivity(): bool
    {
        return match ($this) {
            self::SESSION_START, self::SESSION_SUSPEND, self::SESSION_TERMINATE => true,
            default => false,
        };
    }

    /**
     * Check if activity type involves user interaction
     */
    public function isUserInteraction(): bool
    {
        return match ($this) {
            self::QUESTION_ANSWER, self::INTERACTION => true,
            default => false,
        };
    }
}
