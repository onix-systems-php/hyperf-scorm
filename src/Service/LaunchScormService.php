<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

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
    ) {
    }

    public function run(int $packageId, int $userId): string
    {
        $scormPlayerDto = $this->scormPlayerService->getPlayer($packageId, $userId);
//        $package = $this->scormPackageRepository->findById($packageId);
//        $session = $this->scormUserSessionRepository->findUserSessionForPackage($userId, $packageId);

        return $scormPlayerDto->playerHtml;
    }
}
