<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * Simplified DTO for SCORM Player - contains only essential data for playback
 * Optimized for performance and simplicity
 */
class PlayerScoDTO extends AbstractDTO
{
    public function __construct(
        // Essential for launch
        public readonly string $identifier,        // resource.identifier
        public readonly string $title,             // item.title
        public readonly string $launch_url,        // resource.href + parameters
        
        // Optional SCORM logic
        public readonly ?float $mastery_score = null,     // item['adlcp:masteryscore']
        public readonly ?int $max_time_seconds = null,    // item['adlcp:maxtimeallowed'] in seconds
    ) {}

    /**
     * Check if this SCO has mastery requirements
     */
    public function hasMasteryRequirements(): bool
    {
        return $this->mastery_score !== null && $this->mastery_score > 0;
    }

    /**
     * Check if this SCO has time constraints
     */
    public function hasTimeLimit(): bool
    {
        return $this->max_time_seconds !== null && $this->max_time_seconds > 0;
    }

    /**
     * Get mastery score as percentage (0-100)
     */
    public function getMasteryScorePercentage(): ?int
    {
        if ($this->mastery_score === null) {
            return null;
        }

        return (int) round($this->mastery_score * 100);
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'title' => $this->title,
            'launch_url' => $this->launch_url,
            'mastery_score' => $this->mastery_score,
            'mastery_score_percentage' => $this->getMasteryScorePercentage(),
            'max_time_seconds' => $this->max_time_seconds,
            'has_mastery_requirements' => $this->hasMasteryRequirements(),
            'has_time_limit' => $this->hasTimeLimit(),
        ];
    }
}

