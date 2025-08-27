<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

/**
 * Enhanced DTO for SCORM package data with computed fields
 */
class ScormPackageDataDTO extends AbstractDTO
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $title,
        public readonly ScormVersionEnum $version,
        public readonly ?string $description,
        public readonly int $scoCount,
        public readonly bool $isMultiSco,
        public readonly ?string $entryPoint,
        public readonly string $organizationTitle,
        public readonly ?string $languageCode,
        public readonly ?int $estimatedDuration, // in seconds
        public readonly array $keywords,
        public readonly string $difficulty, // beginner, intermediate, advanced
        public readonly ?string $typicalAgeRange,
        public readonly bool $isAdaptive
    ) {}

    /**
     * Get estimated duration in human readable format
     */
    public function getEstimatedDurationFormatted(): ?string
    {
        if (!$this->estimatedDuration) {
            return null;
        }

        $hours = intval($this->estimatedDuration / 3600);
        $minutes = intval(($this->estimatedDuration % 3600) / 60);

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$minutes}m";
    }

    /**
     * Get package complexity level based on various factors
     */
    public function getComplexityLevel(): string
    {
        $score = 0;

        // SCO count factor
        if ($this->scoCount > 10) {
            $score += 3;
        } elseif ($this->scoCount > 5) {
            $score += 2;
        } else {
            $score += 1;
        }

        // Duration factor
        if ($this->estimatedDuration) {
            if ($this->estimatedDuration > 3600) { // > 1 hour
                $score += 2;
            } elseif ($this->estimatedDuration > 1800) { // > 30 minutes
                $score += 1;
            }
        }

        // Adaptive content factor
        if ($this->isAdaptive) {
            $score += 2;
        }

        // Multi-SCO factor
        if ($this->isMultiSco) {
            $score += 1;
        }

        if ($score >= 6) {
            return 'high';
        } elseif ($score >= 4) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Check if package is suitable for mobile devices
     */
    public function isMobileFriendly(): bool
    {
        // Simple heuristics for mobile compatibility
        return $this->scoCount <= 5 &&
               ($this->estimatedDuration === null || $this->estimatedDuration <= 1800); // 30 minutes
    }

    /**
     * Get package summary for display
     */
    public function getSummary(): array
    {
        return [
            'title' => $this->title,
            'version' => $this->version->value,
            'sco_count' => $this->scoCount,
            'is_multi_sco' => $this->isMultiSco,
            'difficulty' => $this->difficulty,
            'estimated_duration' => $this->getEstimatedDurationFormatted(),
            'complexity_level' => $this->getComplexityLevel(),
            'is_mobile_friendly' => $this->isMobileFriendly(),
            'language' => $this->languageCode,
            'keywords' => $this->keywords
        ];
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'title' => $this->title,
            'version' => $this->version->value,
            'description' => $this->description,
            'sco_count' => $this->scoCount,
            'is_multi_sco' => $this->isMultiSco,
            'entry_point' => $this->entryPoint,
            'organization_title' => $this->organizationTitle,
            'language_code' => $this->languageCode,
            'estimated_duration' => $this->estimatedDuration,
            'estimated_duration_formatted' => $this->getEstimatedDurationFormatted(),
            'keywords' => $this->keywords,
            'difficulty' => $this->difficulty,
            'typical_age_range' => $this->typicalAgeRange,
            'is_adaptive' => $this->isAdaptive,
            'complexity_level' => $this->getComplexityLevel(),
            'is_mobile_friendly' => $this->isMobileFriendly()
        ];
    }
}
