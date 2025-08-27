<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Constants;

/**
 * SCORM CMI elements constants
 */
final class CmiElements
{
    // Core elements
    public const LESSON_STATUS = 'cmi.core.lesson_status';
    public const LESSON_LOCATION = 'cmi.core.lesson_location';
    public const SCORE_RAW = 'cmi.core.score.raw';
    public const SCORE_MIN = 'cmi.core.score.min';
    public const SCORE_MAX = 'cmi.core.score.max';
    public const TOTAL_TIME = 'cmi.core.total_time';
    public const SESSION_TIME = 'cmi.core.session_time';
    public const EXIT = 'cmi.core.exit';
    public const CREDIT = 'cmi.core.credit';
    public const ENTRY = 'cmi.core.entry';
    public const STUDENT_ID = 'cmi.core.student_id';
    public const STUDENT_NAME = 'cmi.core.student_name';
    public const LESSON_MODE = 'cmi.core.lesson_mode';

    // Data elements
    public const SUSPEND_DATA = 'cmi.suspend_data';
    public const LAUNCH_DATA = 'cmi.launch_data';
    public const COMMENTS_FROM_LEARNER = 'cmi.comments_from_learner';
    public const COMMENTS_FROM_LMS = 'cmi.comments_from_lms';

    // Lesson statuses
    public const STATUS_PASSED = 'passed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_BROWSED = 'browsed';
    public const STATUS_NOT_ATTEMPTED = 'not attempted';

    // Entry modes
    public const ENTRY_AB_INITIO = 'ab-initio';
    public const ENTRY_RESUME = 'resume';

    // Exit modes
    public const EXIT_TIME_OUT = 'time-out';
    public const EXIT_SUSPEND = 'suspend';
    public const EXIT_LOGOUT = 'logout';
    public const EXIT_NORMAL = '';

    // Credit modes
    public const CREDIT_CREDIT = 'credit';
    public const CREDIT_NO_CREDIT = 'no-credit';

    // Lesson modes
    public const MODE_BROWSE = 'browse';
    public const MODE_NORMAL = 'normal';
    public const MODE_REVIEW = 'review';
}
