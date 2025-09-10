<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

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
use OnixSystemsPHP\HyperfScorm\Service\ScormFileProcessor;
use function Hyperf\Support\{make, now};

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

    public function getPlayer(int $packageId, $userId, ?string $sessionToken): ScormPlayerDTO
    {
        xdebug_break();

        $package = $this->scormPackageRepository->findById($packageId, true, true);

        $exceptionSession = $sessionToken
            ? $this->scormUserSessionRepository->findByToken($sessionToken)
            : null;

        $session = $exceptionSession ?: $this->createSession($package, $userId);

        $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);

        return ScormPlayerDTO::make([
            'packageId' => $packageId,
            'sessionId' => $session->id,
            'session_token' => $session->session_token,
            'contentUrl' => $this->generateContentUrl($package),
            'launchUrl' => $this->generateLaunchUrl($package),
            'apiConfiguration' => $apiStrategy->getApiConfiguration(),
            'playerHtml' => $this->generatePlayerHtml($package, $session, $apiStrategy),
        ]);
    }

    private function generateSessionToken(): string
    {
        return bin2hex(random_bytes(16));
    }
    private function generateContentUrl(ScormPackage $package): string
    {
        return $this->fileProcessor->getPublicUrl($package->content_path);
    }

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

    private function createSession(ScormPackage $package, int $userId): ScormUserSession
    {
        $session = $this->scormUserSessionRepository->create([
            'package_id' => $package->id,
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

    private function generatePlayerHtml(
        ScormPackage $package,
        ScormUserSession $scormUserSession,
        $apiStrategy
    ): string {
        $launchUrl = $this->generateLaunchUrl($package);
        $apiConfig = $apiStrategy->getApiConfiguration();
        $apiEndpoint = $this->config->get('scorm.player.api_endpoint');
        $render = make(RenderInterface::class);
        $template = $render->getContents('OnixSystemsPHP\\HyperfScorm::player', [
            'package' => $package,
            'user' => $scormUserSession->user,
            'session_token' => $scormUserSession->session_token,
            'launchUrl' => $launchUrl,
            'apiEndpoint' => $apiEndpoint,
            'apiConfig' => $apiConfig,
            'scormVersion' => $package->scorm_version,
        ]);

        return $template;
    }
}
