<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use Carbon\Carbon;

/**
 * Comprehensive result DTO for SCORM package parsing
 * Contains all information needed for database creation and resume functionality
 */
class ScormPackageParseResultDTO extends AbstractDTO
{
    public function __construct(
        public readonly ScormVersionEnum $version,
        public readonly ScormPackageDataDTO $packageData,
        public readonly array $scosData, // Array of ScormScoDataDTO
        public readonly ScormMetadataDTO $metadata,
        public readonly string $entryPoint,
        public readonly array $resumeDataModel, // Resume-related data model elements
        public readonly ScormValidationResultDTO $validationResult
    ) {}

    /**
     * Get database-ready package creation data
     */
    public function getPackageCreationData(): array
    {
        return [
            'identifier' => $this->packageData->identifier,
            'title' => $this->packageData->title,
            'version' => $this->version->value,
            'description' => $this->packageData->description,
            'sco_count' => $this->packageData->scoCount,
            'is_multi_sco' => $this->packageData->isMultiSco,
            'entry_point' => $this->entryPoint,
            'organization_title' => $this->packageData->organizationTitle,
            'language_code' => $this->packageData->languageCode,
            'estimated_duration' => $this->packageData->estimatedDuration,
            'keywords' => json_encode($this->packageData->keywords),
            'difficulty' => $this->packageData->difficulty,
            'typical_age_range' => $this->packageData->typicalAgeRange,
            'is_adaptive' => $this->packageData->isAdaptive,
            'resume_data_model' => json_encode($this->resumeDataModel),
            'metadata' => json_encode($this->metadata->toArray()),
            'validation_status' => $this->validationResult->isValid ? 'valid' : 'invalid',
            'validation_errors' => json_encode($this->validationResult->errors),
            'validation_warnings' => json_encode($this->validationResult->warnings),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ];
    }

    /**
     * Get database-ready SCOs creation data
     */
    public function getScosCreationData(): array
    {
        $scosData = [];

        foreach ($this->scosData as $index => $sco) {
            $scosData[] = [
                'identifier' => $sco['identifier'],
                'title' => $sco['title'],
                'launch_url' => $sco['launch_url'],
                'resource_identifier' => $sco['resource_identifier'],
                'parameters' => $sco['parameters'],
                'prerequisites' => $sco['prerequisites'],
                'maxtimeallowed' => $sco['maxtimeallowed'],
                'timelimitaction' => $sco['timelimitaction'],
                'datafromlms' => $sco['datafromlms'],
                'mastery_score' => $sco['mastery_score'],
                'sequence' => $sco['sequence'],
                'resource_data' => json_encode($sco['resource_data']),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];
        }

        return $scosData;
    }

    /**
     * Get resume data model for specific SCORM version
     */
    public function getResumeDataModel(): array
    {
        return $this->resumeDataModel;
    }

    /**
     * Get location element name for this SCORM version
     */
    public function getLocationElement(): string
    {
        return $this->resumeDataModel['location_element'];
    }

    /**
     * Get status element name for this SCORM version
     */
    public function getStatusElement(): string
    {
        return $this->version === ScormVersionEnum::SCORM_12
            ? $this->resumeDataModel['status_element']
            : $this->resumeDataModel['completion_status_element'];
    }

    /**
     * Get suspend data element name
     */
    public function getSuspendDataElement(): string
    {
        return $this->resumeDataModel['suspend_data_element'];
    }

    /**
     * Get all interaction elements for this version
     */
    public function getInteractionElements(): array
    {
        return [
            'count_element' => $this->resumeDataModel['interactions_count_element'],
            'prefix' => $this->resumeDataModel['interactions_prefix']
        ];
    }

    /**
     * Get all objective elements for this version
     */
    public function getObjectiveElements(): array
    {
        return [
            'count_element' => $this->resumeDataModel['objectives_count_element'],
            'prefix' => $this->resumeDataModel['objectives_prefix']
        ];
    }

    /**
     * Check if package passed validation
     */
    public function isValid(): bool
    {
        return $this->validationResult->isValid;
    }

    /**
     * Get validation summary
     */
    public function getValidationSummary(): string
    {
        if ($this->validationResult->isValid) {
            $warningCount = count($this->validationResult->warnings);
            return $warningCount > 0
                ? "Valid with {$warningCount} warning(s)"
                : "Valid";
        }

        $errorCount = count($this->validationResult->errors);
        return "Invalid with {$errorCount} error(s)";
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version->value,
            'package_data' => $this->packageData->toArray(),
            'scos_data' => $this->scosData,
            'metadata' => $this->metadata->toArray(),
            'entry_point' => $this->entryPoint,
            'resume_data_model' => $this->resumeDataModel,
            'validation_result' => $this->validationResult->toArray()
        ];
    }
}
