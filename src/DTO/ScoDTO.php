<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for SCORM Sharable Content Object (SCO)
 * Represents a single content object with all necessary data for playback
 */
class ScoDTO extends AbstractDTO
{

    public string $identifier;     // resource.identifier
    public string $title;          // item.title
    public string $launch_url;       // resource.href + parameters
    public ?float $mastery_score;     // item['adlcp:masteryscore']
    public ?int $max_time_seconds = 0;


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

        return (int)round($this->mastery_score * 100);
    }

    /**
     * Get array representation suitable for ScormSco model creation
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
