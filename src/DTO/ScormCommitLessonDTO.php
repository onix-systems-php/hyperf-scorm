<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormCommitLessonDTO extends AbstractDTO
{
    public string $credit;

    public ?string $entry = null;

    public string $exit = 'suspend';

    public ?string $location = null;

    public string $mode = 'normal';

    public string $status = 'completed';
}
