<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Action;

use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;
use OnixSystemsPHP\HyperfScorm\DTO\PlayerManifestDTO;
use OnixSystemsPHP\HyperfScorm\Service\ScormPlayerService;
use OnixSystemsPHP\HyperfScorm\Service\PlayerManifestParser;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;

/**
 * Action for getting SCORM player with optimized data
 * Uses simplified DTOs for better performance
 */
class GetScormPlayerAction
{
    public function __construct(
        private readonly ScormPlayerService $playerService,
        private readonly PlayerManifestParser $manifestParser,
        private readonly ScormPackageRepository $packageRepository
    ) {
    }

    /**
     * Get SCORM player with optimized manifest data
     */
    public function execute(int $packageId, int $userId): ScormPlayerDTO
    {
        // Get package
        $package = $this->packageRepository->findById($packageId);
        if (!$package) {
            throw new \InvalidArgumentException("SCORM package not found: {$packageId}");
        }

        // Parse manifest for player-optimized data
        $playerManifest = $this->manifestParser->parse($package->manifest_data);
        
        if (!$playerManifest->hasScos()) {
            throw new \RuntimeException("No SCOs found in package: {$packageId}");
        }

        // Get player data
        return $this->playerService->getPlayer($packageId, $userId);
    }

    /**
     * Get player manifest data only (without player HTML)
     */
    public function getManifestData(int $packageId): PlayerManifestDTO
    {
        $package = $this->packageRepository->findById($packageId);
        if (!$package) {
            throw new \InvalidArgumentException("SCORM package not found: {$packageId}");
        }

        return $this->manifestParser->parse($package->manifest_data);
    }

    /**
     * Get specific SCO by identifier
     */
    public function getScoByIdentifier(int $packageId, string $identifier): ?\OnixSystemsPHP\HyperfScorm\DTO\PlayerScoDTO
    {
        $manifest = $this->getManifestData($packageId);
        return $manifest->getScoByIdentifier($identifier);
    }
}

