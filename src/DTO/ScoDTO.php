<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for SCORM Sharable Content Object (SCO)
 * Represents a single content object with all necessary data for playback.
 */
class ScoDTO extends AbstractDTO
{
    public string $identifier;

    public string $title;

    public string $launch_url;

    public ?float $mastery_score;

    public ?int $max_time_seconds = 0;

    public function hasMasteryRequirements(): bool
    {
        return $this->mastery_score !== null && $this->mastery_score > 0;
    }

    public function hasTimeLimit(): bool
    {
        return $this->max_time_seconds !== null && $this->max_time_seconds > 0;
    }

    public function getMasteryScorePercentage(): ?int
    {
        if ($this->mastery_score === null) {
            return null;
        }

        return (int) round($this->mastery_score * 100);
    }

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
