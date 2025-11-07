<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormAsyncJobDTO extends AbstractDTO
{
    public string $job_id;

    public string $status;

    public int $progress;

    public string $stage;

    public int $estimated_time;
}
