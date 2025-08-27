<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use Carbon\Carbon;

/**
 * Enhanced DTO for SCORM metadata with structured information
 */
class ScormMetadataDTO extends AbstractDTO
{
    public function __construct(
        public readonly array $originalMetadata,
        public readonly ScormVersionEnum $version,
        public readonly string $schemaVersion,
        public readonly ?string $catalog,
        public readonly ?string $entry,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly array $keywords,
        public readonly ?string $coverage,
        public readonly ?string $structure,
        public readonly ?string $aggregationLevel,
        public readonly ?string $status,
        public readonly array $contribute,
        public readonly ?string $format,
        public readonly ?string $size,
        public readonly ?string $location,
        public readonly array $requirements,
        public readonly ?string $installationRemarks,
        public readonly ?string $otherPlatformRequirements,
        public readonly ?string $duration,
        public readonly Carbon $extractedAt
    ) {}

    /**
     * Get contributors by role
     */
    public function getContributorsByRole(string $role): array
    {
        $contributors = [];

        foreach ($this->contribute as $contribution) {
            if (isset($contribution['role']) && $contribution['role'] === $role) {
                $contributors[] = $contribution;
            }
        }

        return $contributors;
    }

    /**
     * Get authors
     */
    public function getAuthors(): array
    {
        return $this->getContributorsByRole('author');
    }

    /**
     * Get publishers
     */
    public function getPublishers(): array
    {
        return $this->getContributorsByRole('publisher');
    }

    /**
     * Get technical requirements summary
     */
    public function getTechnicalRequirements(): array
    {
        $summary = [
            'format' => $this->format,
            'size' => $this->size,
            'location' => $this->location,
            'requirements' => $this->requirements,
            'installation_remarks' => $this->installationRemarks,
            'other_platform_requirements' => $this->otherPlatformRequirements
        ];

        return array_filter($summary, fn($value) => $value !== null);
    }

    /**
     * Check if metadata is complete
     */
    public function isComplete(): bool
    {
        $requiredFields = ['title', 'description'];

        foreach ($requiredFields as $field) {
            if (empty($this->$field)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get metadata quality score (0-100)
     */
    public function getQualityScore(): int
    {
        $score = 0;
        $maxScore = 100;

        // Title (20 points)
        if (!empty($this->title)) {
            $score += 20;
        }

        // Description (20 points)
        if (!empty($this->description)) {
            $score += 20;
        }

        // Keywords (15 points)
        if (!empty($this->keywords)) {
            $score += 15;
        }

        // Contributors (15 points)
        if (!empty($this->contribute)) {
            $score += 15;
        }

        // Technical info (10 points)
        if (!empty($this->requirements) || !empty($this->format)) {
            $score += 10;
        }

        // Additional fields (20 points)
        $additionalFields = [$this->coverage, $this->structure, $this->aggregationLevel, $this->duration];
        $filledFields = count(array_filter($additionalFields, fn($field) => !empty($field)));
        $score += intval(($filledFields / 4) * 20);

        return min($score, $maxScore);
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'original_metadata' => $this->originalMetadata,
            'version' => $this->version->value,
            'schema_version' => $this->schemaVersion,
            'catalog' => $this->catalog,
            'entry' => $this->entry,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'coverage' => $this->coverage,
            'structure' => $this->structure,
            'aggregation_level' => $this->aggregationLevel,
            'status' => $this->status,
            'contribute' => $this->contribute,
            'format' => $this->format,
            'size' => $this->size,
            'location' => $this->location,
            'requirements' => $this->requirements,
            'installation_remarks' => $this->installationRemarks,
            'other_platform_requirements' => $this->otherPlatformRequirements,
            'duration' => $this->duration,
            'extracted_at' => $this->extractedAt->toISOString(),
            'authors' => $this->getAuthors(),
            'publishers' => $this->getPublishers(),
            'technical_requirements' => $this->getTechnicalRequirements(),
            'is_complete' => $this->isComplete(),
            'quality_score' => $this->getQualityScore()
        ];
    }
}
