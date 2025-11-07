<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;

#[Service]
class LaunchScormService
{
    public const ACTION = 'launch_scorm';

    public function __construct(
        public readonly ScormPackageRepository $scormPackageRepository,
        public readonly ScormUserSessionRepository $scormUserSessionRepository,
        public readonly ScormPlayerService $scormPlayerService,
    ) {}

    public function run(int $packageId): string
    {
        $scormPlayerDto = $this->scormPlayerService->run($packageId);
        return $scormPlayerDto->playerHtml;
    }
}
