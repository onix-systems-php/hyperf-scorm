<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Constants;

/**
 * SCORM attempt status constants.
 */
final class AttemptStatuses
{
    public const NOT_ATTEMPTED = 'not_attempted';

    public const INCOMPLETE = 'incomplete';

    public const COMPLETED = 'completed';

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public const BROWSED = 'browsed';

    public const ALL = [
        self::NOT_ATTEMPTED,
        self::INCOMPLETE,
        self::COMPLETED,
        self::PASSED,
        self::FAILED,
        self::BROWSED,
    ];

    public const LABELS = [
        self::NOT_ATTEMPTED => 'Not Attempted',
        self::INCOMPLETE => 'Incomplete',
        self::COMPLETED => 'Completed',
        self::PASSED => 'Passed',
        self::FAILED => 'Failed',
        self::BROWSED => 'Browsed',
    ];

    public const FINAL_STATUSES = [
        self::COMPLETED,
        self::PASSED,
        self::FAILED,
    ];
}
