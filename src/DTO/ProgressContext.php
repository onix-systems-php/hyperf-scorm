<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ProgressContext extends AbstractDTO
{
    public string $jobId;

    public int $userId;

    public int $fileSize;

    public bool $isRetryable = true;
}
