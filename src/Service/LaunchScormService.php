<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\ScormPlayerService;

#[Service]
class LaunchScormService
{
    public const ACTION = 'launch_scorm';

    public function __construct(
        public readonly ScormPackageRepository $scormPackageRepository,
        public readonly ScormUserSessionRepository $scormUserSessionRepository,
        public readonly ScormPlayerService $scormPlayerService,
    ) {
    }

    public function run(int $packageId, int $userId, ?string $sessionToken): string
    {
        $scormPlayerDto = $this->scormPlayerService->run($packageId, $userId, $sessionToken);
        return $scormPlayerDto->playerHtml;
    }
}
