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
    public string $credit; // todo create field in db

    public ?string $entry = null; // todo create field in db

    public string $exit = 'suspend'; // todo in db exit mode

    public ?string $location = null;

    public string $mode = 'normal'; // todo in db exit mode

    public string $status = 'completed';
}
