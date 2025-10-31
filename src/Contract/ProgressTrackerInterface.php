<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Contract;

use OnixSystemsPHP\HyperfScorm\DTO\ProgressContext;

interface ProgressTrackerInterface
{
    public function track(ProgressContext $context, array $progressData): void;
}
