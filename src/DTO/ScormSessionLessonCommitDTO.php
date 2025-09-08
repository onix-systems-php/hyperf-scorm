<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormSessionLessonCommitDTO extends AbstractDTO
{
    public string $credit;

    public ?string $entry = null;

    public string $exit = 'suspend';

    public ?string $location = null;

    public string $mode = 'normal';

    public string $status = 'completed';

}
