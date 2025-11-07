<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * Context for progress tracking
 * Immutable value object carrying job identification data.
 *
 * @property string $jobId Job identifier for tracking
 * @property int $userId User who initiated the upload
 * @property int $fileSize Size of uploaded file in bytes
 * @property bool $isRetryable Whether the job can be retried on failure
 */
class ProgressContext extends AbstractDTO
{
    public string $jobId;

    public int $userId;

    public int $fileSize;

    /**
     * Whether this job can be retried on failure.
     *
     * - true: Job can retry (attempts 1-2 of 3) - don't notify user on error
     * - false: Final attempt or called from Job::failed() - notify user on error
     */
    public bool $isRetryable = true;
}
