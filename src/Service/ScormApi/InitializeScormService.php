<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Constants\SessionStatuses;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;
use function Hyperf\Support\now;

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

    public function run(int $packageId, int $userId): ScormUserSession
    {
        $session = $this->scormUserSessionRepository->findByProjectAndUser($packageId, $userId)
            ?? $this->createSession($packageId, $userId);

        $session->load(['user', 'interactions']);

        return $session;
    }

    private function createSession(int $packageId, int $userId): ScormUserSession
    {
        $session = $this->scormUserSessionRepository->create([
            'package_id' => $packageId,
            'user_id' => $userId,
            'session_token' => $this->generateSessionToken(),
            'status' => SessionStatuses::BROWSED,
            'lesson_location' => '',
            'suspend_data' => [],
            'started_at' => now(),
        ]);

        $this->scormUserSessionRepository->save($session);

        return $session;
    }

    private function generateSessionToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
