<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * Simplified manifest DTO for SCORM Player
 * Contains only essential data needed for playback
 */
class PlayerManifestDTO extends AbstractDTO
{
    public function __construct(
        public readonly string $title,                    // manifest.metadata.lom.general.title
        public readonly string $version,                  // manifest.version
        public readonly string $scorm_version,            // manifest.metadata.schema
        public readonly array $scos,                      // PlayerScoDTO[]
        public readonly ?string $description = null,      // manifest.metadata.lom.general.description
    ) {}

    /**
     * Get the primary SCO for launch (first SCO in the list)
     */
    public function getPrimarySco(): ?PlayerScoDTO
    {
        return $this->scos[0] ?? null;
    }

    /**
     * Get the primary launch URL
     */
    public function getPrimaryLaunchUrl(): ?string
    {
        $primarySco = $this->getPrimarySco();
        return $primarySco?->launch_url;
    }

    /**
     * Get SCO by identifier
     */
    public function getScoByIdentifier(string $identifier): ?PlayerScoDTO
    {
        foreach ($this->scos as $sco) {
            if ($sco->identifier === $identifier) {
                return $sco;
            }
        }
        
        return null;
    }

    /**
     * Check if manifest has any SCOs
     */
    public function hasScos(): bool
    {
        return !empty($this->scos);
    }

    /**
     * Get total number of SCOs
     */
    public function getScoCount(): int
    {
        return count($this->scos);
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'version' => $this->version,
            'scorm_version' => $this->scorm_version,
            'description' => $this->description,
            'scos' => array_map(fn($sco) => $sco->toArray(), $this->scos),
            'primary_launch_url' => $this->getPrimaryLaunchUrl(),
            'sco_count' => $this->getScoCount(),
        ];
    }
}

