<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormCommitSessionDTO extends AbstractDTO
{
    public int $total_time = 0;

    public int $session_time = 0;

    public int $session_time_seconds = 0;

    public string $suspend_data = '{}';
}
