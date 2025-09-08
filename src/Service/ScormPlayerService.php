<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\View\RenderInterface;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Constants\SessionStatuses;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;
use OnixSystemsPHP\HyperfScorm\Factory\ScormApiStrategyFactory;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;
use function Hyperf\Support\{make,now};

/**
 * SCORM Player Service - generates player content with session restoration
 * Supports both SCORM 1.2 and SCORM 2004 with user progress restoration
 */
#[Service]
class ScormPlayerService
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly ScormUserSessionRepository $scormUserSessionRepository,
        private readonly ScormApiStrategyFactory $apiStrategyFactory,
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ConfigInterface $config
    ) {
    }

    /**
     * Generate SCORM player with session restoration capability
     */
    public function getPlayer(int $packageId, int $userId): ScormPlayerDTO
    {
        $package = $this->scormPackageRepository->findById($packageId, true, true);
//        if (!$package) {
//            throw new \InvalidArgumentException("SCORM package not found: {$packageId}");
//        }

        $scormUserSession = $this->resolveUserSession($package, $userId);
        $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);


        return ScormPlayerDTO::make([
            'packageId' => $packageId,
            'sessionId' => $scormUserSession->id,
            'contentUrl' => $this->generateContentUrl($package),
            'launchUrl' => $this->generateLaunchUrl($package),
            'apiConfiguration' => $apiStrategy->getApiConfiguration(),
            'sessionData' => $this->prepareSessionData($scormUserSession),
            'playerHtml' => $this->generatePlayerHtml($package, $scormUserSession, $apiStrategy),
        ]);
    }

    private function resolveUserSession(ScormPackage $package, int $userId): ScormUserSession
    {
        $session = $this->scormUserSessionRepository->findUserSessionForPackage($package->id, $userId);

        if (!$session) {
            $session = $this->scormUserSessionRepository->create([
                    'package_id' => $package->id,
                    'user_id' => $userId,
                    'status' => SessionStatuses::BROWSED,
                    'current_location' => '',
                    'suspend_data' => [],
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);

            $this->scormUserSessionRepository->save($session);
        }

        return $session;
    }

    /**
     * Get initial CMI data based on SCORM version
     */
    private function getInitialCmiData(string $scormVersion): array
    {
        $baseData = [
            'cmi.core.lesson_status' => 'not attempted',
            'cmi.core.student_id' => '',
            'cmi.core.student_name' => '',
            'cmi.core.lesson_location' => '',
            'cmi.suspend_data' => '',
        ];

        if ($scormVersion === '2004') {
            $baseData['cmi.completion_status'] = 'not attempted';
            $baseData['cmi.success_status'] = 'unknown';
            $baseData['cmi.learner_id'] = '';
            $baseData['cmi.learner_name'] = '';
            $baseData['cmi.location'] = '';
            $baseData['cmi.progress_measure'] = '';
        }

        return $baseData;
    }

    /**
     * Generate secure content URL for SCORM package
     */
    private function generateContentUrl(ScormPackage $package): string
    {
        return $this->fileProcessor->getPublicUrl($package->content_path);
    }

    /**
     * Generate launch URL for the main SCO
     */
    private function generateLaunchUrl(ScormPackage $package): string
    {
        $primaryLaunchUrl = $package->manifest_data->getPrimaryLaunchUrl();
        if (!$primaryLaunchUrl) {
            throw new \RuntimeException("No SCOs found for package: {$package->id}");
        }

        return $this->fileProcessor->getPublicUrl(
            $package->content_path,
            $primaryLaunchUrl
        );
    }

    /**
     * Prepare session data for restoration
     */
    private function prepareSessionData(ScormUserSession $attempt): array
    {
        return [
            'attemptId' => $attempt->id,
            'status' => $attempt->status ?? 'not attempted',
            'suspendData' => $attempt->suspend_data ?? [],
            'currentLocation' => $attempt->current_location ?? '',
            'startedAt' => $attempt->started_at ? $attempt->started_at->toISOString() : null,
        ];
    }

    private function generatePlayerHtml(
        ScormPackage $package,
        ScormUserSession $scormUserSession,
        $apiStrategy
    ): string {
        $launchUrl = $this->generateLaunchUrl($package);
        $apiConfig = $apiStrategy->getApiConfiguration();
        $apiEndpoint = $this->config->get('scorm.player.api_endpoint', '/v1/scorm/api');
        $render = make(RenderInterface::class);
        $template = $render->getContents('OnixSystemsPHP\\HyperfScorm::player', [
            'package' => $package,
            'launchUrl' => $launchUrl,
            'apiEndpoint' => $apiEndpoint,
            'apiConfig' => $apiConfig,
            'sessionId' => $scormUserSession->id,
            'scormVersion' => $package->scorm_version,
        ]);

        return $template;
    }
}
