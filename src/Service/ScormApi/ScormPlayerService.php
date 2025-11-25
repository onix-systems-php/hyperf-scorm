<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use Hyperf\Redis\Redis;
use Hyperf\View\RenderInterface;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;

#[Service]
class ScormPlayerService
{
    private const USER_ID_PLACEHOLDER = '{{USER_ID}}';

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly RenderInterface $render,
        private readonly Redis $redis,
    ) {
    }

    public function run(int $packageId, $userId): ScormPlayerDTO
    {
        $package = $this->scormPackageRepository->getById($packageId, false, true);
        $playerHtml = $this->getPlayerHtml($package, $userId);

        return ScormPlayerDTO::make([
            'package' => $package->toArray(),
            'playerHtml' => $playerHtml,
        ]);
    }

    private function getPlayerHtml(
        ScormPackage $package,
        int $userId,
    ): string {

        $cacheKey = $this->generateCacheKey($package);
        $template = $this->redis->get($cacheKey);

        if ($template === false || !is_string($template)) {
            $template = $this->renderTemplate($package);
            $this->redis->setex($cacheKey, self::CACHE_TTL, $template);
        }

        return str_replace(self::USER_ID_PLACEHOLDER, (string)$userId, $template);
    }

    private function generateCacheKey(ScormPackage $package):string
    {
        return sprintf(
            'scorm:player_template:%d:%s:%d',
            $package->id,
            $package->scorm_version,
            $package->updated_at->timestamp
        );
    }

    private function renderTemplate(ScormPackage $package) :string
    {
       return $this->render->getContents('OnixSystemsPHP\HyperfScorm::player', [
            'package' => $package,
            'user' => [
                'id' => self::USER_ID_PLACEHOLDER,
                'session_token' => null,
            ],
            'scorm' => [
                'timeout' => config('scorm.player.timeout'),
                'debug' => config('scorm.player.debug'),
                'autoCommitInterval' => config('scorm.tracking.auto_commit_interval'),
                'version' => $package->scorm_version,
                'launcherPath' => $package->launcher_path,
            ],
        ]);
    }
}
