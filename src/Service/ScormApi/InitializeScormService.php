<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;

#[Service]
class InitializeScormService
{
    public const ACTION = 'initialize_scorm';

    public function __construct(
        public readonly ScormPackageRepository $scormPackageRepository,
        public readonly ScormUserSessionRepository $scormUserSessionRepository,
        public readonly ScormPlayerService $scormPlayerService,
    ) {
    }

    public function run(int $packageId, string $sessionToken): ScormUserSession
    {
        //todo create session_id hash uuid, but if int you cant hack others sessions
        xdebug_break();

        $session = $this->scormUserSessionRepository->findByIdentifier($packageId, $sessionToken);
        $session->load(['interactions']);

        return $session;
    }
}
