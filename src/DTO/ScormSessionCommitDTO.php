<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormSessionCommitDTO extends AbstractDTO
{
    public int $total_time = 0;

    public int $session_time = 0;

    public int $session_time_seconds;

    public string $suspend_data;
}
