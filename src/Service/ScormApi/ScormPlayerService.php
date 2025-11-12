<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use Hyperf\Contract\ConfigInterface;
use Hyperf\View\RenderInterface;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\Strategy\ScormApiStrategyFactory;
use function Hyperf\Config\config;
use function Hyperf\Support\make;

/**
 * SCORM Player Service - generates player content with session restoration
 * Supports both SCORM 1.2 and SCORM 2004 with user progress restoration.
 */
#[Service]
class ScormPlayerService
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly ScormApiStrategyFactory $apiStrategyFactory,
    ) {
    }

    public function run(int $packageId, $userId): ScormPlayerDTO
    {
        $package = $this->scormPackageRepository->findById($packageId, true, true);

        $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);

        return ScormPlayerDTO::make([
            'package' => $package->toArray(),
            'playerHtml' => $this->generatePlayerHtml($package, $userId, $apiStrategy),
        ]);
    }

    private function generatePlayerHtml(
        ScormPackage $package,
        int $userId,
        $apiStrategy
    ): string {
        $apiConfig = $apiStrategy->getApiConfiguration();
        $render = make(RenderInterface::class);
        return $render->getContents('OnixSystemsPHP\HyperfScorm::player', [
            'package' => $package,
            'user' => [
                'id' => $userId,
                'session_token' => null,
            ],
            'scorm' => [
                'timeout' =>  config('scorm.player.timeout'),
                'debug' => config('scorm.player.debug'),
                'autoCommitInterval' => config('scorm.tracking.auto_commit_interval'),
                'version' => $package->scorm_version,
                'launchUrl' => $package->launch_url,
            ],
            'apiConfig' => $apiConfig,
        ]);
    }
}
