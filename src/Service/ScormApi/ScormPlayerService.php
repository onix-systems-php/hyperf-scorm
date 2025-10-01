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

    public function run(int $packageId): ScormPlayerDTO
    {
        $package = $this->scormPackageRepository->findById($packageId, true, true);

        $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);

        return ScormPlayerDTO::make([
            'packageId' => $packageId,
            'contentUrl' => $this->generateContentUrl($package),
            'launchUrl' => $this->generateLaunchUrl($package),
            'apiConfiguration' => $apiStrategy->getApiConfiguration(),
            'playerHtml' => $this->generatePlayerHtml($package, $apiStrategy),
        ]);
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

    private function generatePlayerHtml(
        ScormPackage $package,
        $apiStrategy
    ): string {
        $launchUrl = $this->generateLaunchUrl($package);
        $apiConfig = $apiStrategy->getApiConfiguration();
        $apiEndpoint = $this->config->get('scorm.player.api_endpoint');
        $render = make(RenderInterface::class);
        $template = $render->getContents('OnixSystemsPHP\\HyperfScorm::player', [
            'package' => $package,
//            'user' => [
//                'id' => null,
//                'name' => 'Guest User',
//            ],
            'session_token' => null,
            'launchUrl' => $launchUrl,
            'apiEndpoint' => $apiEndpoint,
            'apiConfig' => $apiConfig,
            'scormVersion' => $package->scorm_version,
        ]);

        return $template;
    }
}
