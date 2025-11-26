<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Contract;

use OnixSystemsPHP\HyperfScorm\DTO\ProgressContext;

interface ProgressTrackerInterface
{
    public function track(ProgressContext $context, array $progressData): void;
}
