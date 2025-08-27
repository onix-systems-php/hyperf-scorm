<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPackageDataDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormMetadataDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use Carbon\Carbon;

/**
 * Service for enriching SCORM package data with additional computed information
 */
class ScormDataEnricher
{
    /**
     * Enrich package data with computed fields and metadata
     */
    public function enrichPackageData(ScormManifestDTO $manifest): ScormPackageDataDTO
    {
        return new ScormPackageDataDTO(
            identifier: $manifest->identifier,
            title: $manifest->title,
            version: $manifest->version,
            description: $this->extractDescription($manifest),
            scoCount: count($manifest->scos),
            isMultiSco: $manifest->isMultiSco(),
            entryPoint: $this->determineEntryPoint($manifest),
            organizationTitle: $manifest->title,
            languageCode: $this->extractLanguage($manifest),
            estimatedDuration: $this->estimateDuration($manifest),
            keywords: $this->extractKeywords($manifest),
            difficulty: $this->determineDifficulty($manifest),
            typicalAgeRange: $this->extractTypicalAgeRange($manifest),
            isAdaptive: $this->checkIfAdaptive($manifest)
        );
    }

    /**
     * Enrich metadata with additional computed information
     */
    public function enrichMetadata(array $originalMetadata, ScormVersionEnum $version): ScormMetadataDTO
    {
        return new ScormMetadataDTO(
            originalMetadata: $originalMetadata,
            version: $version,
            schemaVersion: $this->extractSchemaVersion($originalMetadata, $version),
            catalog: $originalMetadata['catalog'] ?? null,
            entry: $originalMetadata['entry'] ?? null,
            title: $originalMetadata['title'] ?? null,
            description: $originalMetadata['description'] ?? null,
            keywords: $this->extractKeywordsFromMetadata($originalMetadata),
            coverage: $originalMetadata['coverage'] ?? null,
            structure: $originalMetadata['structure'] ?? null,
            aggregationLevel: $originalMetadata['aggregationLevel'] ?? null,
            status: $originalMetadata['status'] ?? null,
            contribute: $originalMetadata['contribute'] ?? [],
            format: $originalMetadata['format'] ?? null,
            size: $originalMetadata['size'] ?? null,
            location: $originalMetadata['location'] ?? null,
            requirements: $this->extractRequirements($originalMetadata),
            installationRemarks: $originalMetadata['installationRemarks'] ?? null,
            otherPlatformRequirements: $originalMetadata['otherPlatformRequirements'] ?? null,
            duration: $originalMetadata['duration'] ?? null,
            extractedAt: Carbon::now()
        );
    }

    /**
     * Extract description from manifest metadata
     */
    private function extractDescription(ScormManifestDTO $manifest): ?string
    {
        // Try to get description from metadata first
        if (!empty($manifest->metadata['description'])) {
            return $manifest->metadata['description'];
        }

        // Fall back to first SCO title if available
        $firstSco = $manifest->getFirstSco();
        if ($firstSco && $firstSco->title !== $manifest->title) {
            return $firstSco->title;
        }

        return null;
    }

    /**
     * Determine main entry point for the package
     */
    private function determineEntryPoint(ScormManifestDTO $manifest): ?string
    {
        $firstSco = $manifest->getFirstSco();
        return $firstSco?->launch_url;
    }

    /**
     * Extract language code from manifest
     */
    private function extractLanguage(ScormManifestDTO $manifest): ?string
    {
        // Check metadata for language
        if (!empty($manifest->metadata['language'])) {
            return $manifest->metadata['language'];
        }

        // No organization-specific language in new structure
        // Could be extended to check SCO-level language if needed

        return 'en'; // Default to English
    }

    /**
     * Estimate duration based on SCO count and metadata
     */
    private function estimateDuration(ScormManifestDTO $manifest): ?int
    {
        // Check if duration is specified in metadata
        if (!empty($manifest->metadata['duration'])) {
            return $this->parseDuration($manifest->metadata['duration']);
        }

        // Estimate based on SCO count (rough estimate: 10 minutes per SCO)
        $scoCount = count($manifest->scos);
        return $scoCount * 600; // 600 seconds = 10 minutes
    }

    /**
     * Parse ISO 8601 duration string to seconds
     */
    private function parseDuration(string $duration): int
    {
        try {
            $interval = new \DateInterval($duration);
            return ($interval->d * 24 * 60 * 60) +
                   ($interval->h * 60 * 60) +
                   ($interval->i * 60) +
                   $interval->s;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Extract keywords from manifest metadata
     */
    private function extractKeywords(ScormManifestDTO $manifest): array
    {
        $keywords = [];

        // From metadata
        if (!empty($manifest->metadata['keyword'])) {
            if (is_array($manifest->metadata['keyword'])) {
                $keywords = array_merge($keywords, $manifest->metadata['keyword']);
            } else {
                $keywords[] = $manifest->metadata['keyword'];
            }
        }

        // From title (extract meaningful words)
        $titleWords = $this->extractWordsFromTitle($manifest->title);
        $keywords = array_merge($keywords, $titleWords);

        return array_unique(array_filter($keywords));
    }

    /**
     * Extract meaningful words from title
     */
    private function extractWordsFromTitle(string $title): array
    {
        $words = preg_split('/\s+/', strtolower($title));
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        return array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }

    /**
     * Determine difficulty level based on manifest data
     */
    private function determineDifficulty(ScormManifestDTO $manifest): string
    {
        // Check metadata for difficulty
        if (!empty($manifest->metadata['difficulty'])) {
            return $manifest->metadata['difficulty'];
        }

        // Estimate based on SCO count and complexity
        $scoCount = count($manifest->scos);

        // Also check if any SCOs have mastery requirements
        $hasMasteryRequirements = false;
        foreach ($manifest->scos as $sco) {
            if ($sco->hasMasteryRequirements()) {
                $hasMasteryRequirements = true;
                break;
            }
        }

        if ($scoCount <= 3 && !$hasMasteryRequirements) {
            return 'beginner';
        } elseif ($scoCount <= 10) {
            return 'intermediate';
        } else {
            return 'advanced';
        }
    }

    /**
     * Extract typical age range from metadata
     */
    private function extractTypicalAgeRange(ScormManifestDTO $manifest): ?string
    {
        return $manifest->metadata['typicalAgeRange'] ?? null;
    }

    /**
     * Check if content appears to be adaptive
     */
    private function checkIfAdaptive(ScormManifestDTO $manifest): bool
    {
        // Look for adaptive content indicators in SCOs
        foreach ($manifest->scos as $sco) {
            // Check for prerequisites, mastery scores, or time constraints
            if ($sco->hasPrerequisites() || $sco->hasMasteryRequirements() || $sco->hasTimeConstraints()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract schema version from metadata
     */
    private function extractSchemaVersion(array $metadata, ScormVersionEnum $version): string
    {
        if (!empty($metadata['schemaversion'])) {
            return $metadata['schemaversion'];
        }

        return $version === ScormVersionEnum::SCORM_12 ? '1.2' : '2004';
    }

    /**
     * Extract keywords from metadata specifically
     */
    private function extractKeywordsFromMetadata(array $metadata): array
    {
        $keywords = [];

        if (!empty($metadata['keyword'])) {
            if (is_array($metadata['keyword'])) {
                $keywords = $metadata['keyword'];
            } else {
                $keywords = [$metadata['keyword']];
            }
        }

        return array_unique(array_filter($keywords));
    }

    /**
     * Extract technical requirements from metadata
     */
    private function extractRequirements(array $metadata): array
    {
        $requirements = [];

        if (!empty($metadata['requirement'])) {
            if (is_array($metadata['requirement'])) {
                $requirements = $metadata['requirement'];
            } else {
                $requirements = [$metadata['requirement']];
            }
        }

        return $requirements;
    }
}
