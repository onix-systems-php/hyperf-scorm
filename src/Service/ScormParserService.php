<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Service\ScormManifestParser;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPackageParseResultDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPackageDataDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormValidationResultDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OnixSystemsPHP\HyperfScorm\Exception\ScormParsingException;
use OnixSystemsPHP\HyperfScorm\Exception\ScormValidationException;

/**
 * Enhanced SCORM Parser Service
 *
 * Coordinates all SCORM parsing operations including:
 * - Manifest parsing and validation
 * - Resume functionality data extraction
 * - Comprehensive package analysis
 * - Database-ready data preparation
 */
class ScormParserService
{
    public function __construct(
        private readonly ScormManifestParser $manifestParser,
        private readonly ScormDataEnricher $dataEnricher,
        private readonly ScormValidator $validator
    ) {}

    /**
     * Parse SCORM package and return comprehensive result with resume support
     */
    public function parseScormPackage(string $manifestPath): ScormPackageParseResultDTO
    {
        // Step 1: Parse the manifest
        $manifestData = $this->manifestParser->parse($manifestPath);

        // Step 2: Validate the package structure
        $validationResult = $this->validator->validateManifest($manifestData);
        if (!$validationResult->isValid) {
            throw new ScormValidationException($validationResult->getErrorSummary());
        }

        // Step 3: Extract resume-related data model elements
        $resumeDataModel = $this->extractResumeDataModel($manifestData);

        // Step 4: Enrich package data
        $enrichedPackageData = $this->dataEnricher->enrichPackageData($manifestData);

        // Step 5: Extract SCO data
        $scosData = $this->extractScosData($manifestData);

        // Step 6: Extract metadata
        $metadata = $this->dataEnricher->enrichMetadata($manifestData->metadata, $manifestData->version);

        return new ScormPackageParseResultDTO(
            version: $manifestData->version,
            packageData: $enrichedPackageData,
            scosData: $scosData,
            metadata: $metadata,
            entryPoint: $this->determineEntryPoint($manifestData),
            resumeDataModel: $resumeDataModel,
            validationResult: $validationResult
        );
    }

    /**
     * Detect SCORM version from manifest file
     */
    public function detectVersion(string $manifestPath): ScormVersionEnum
    {
        if (!file_exists($manifestPath)) {
            throw new ScormParsingException("Manifest file not found: {$manifestPath}");
        }

        $manifestData = $this->manifestParser->parse($manifestPath);
        return $manifestData->version;
    }

    /**
     * Validate SCORM package structure and content
     */
    public function validatePackage(string $packagePath): ScormValidationResultDTO
    {
        $manifestPath = $packagePath . '/imsmanifest.xml';

        if (!file_exists($manifestPath)) {
            return ScormValidationResultDTO::createInvalid([
                'Missing imsmanifest.xml file'
            ]);
        }

        try {
            $manifestData = $this->manifestParser->parse($manifestPath);
            return $this->validator->validatePackageStructure($packagePath, $manifestData);
        } catch (\Exception $e) {
            return ScormValidationResultDTO::createInvalid([
                'Manifest parsing failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Extract resume-related data model elements based on SCORM version
     */
    private function extractResumeDataModel(ScormManifestDTO $manifest): array
    {
        $baseModel = [
            'suspend_data_element' => 'cmi.suspend_data',
            'session_time_element' => $this->getSessionTimeElement($manifest->version),
            'total_time_element' => $this->getTotalTimeElement($manifest->version),
        ];

        if ($manifest->version === ScormVersionEnum::SCORM_12) {
            return array_merge($baseModel, [
                'location_element' => 'cmi.core.lesson_location',
                'status_element' => 'cmi.core.lesson_status',
                'score_raw_element' => 'cmi.core.score.raw',
                'score_min_element' => 'cmi.core.score.min',
                'score_max_element' => 'cmi.core.score.max',
                'entry_element' => 'cmi.core.entry',
                'exit_element' => 'cmi.core.exit',
                'interactions_count_element' => 'cmi.interactions._count',
                'interactions_prefix' => 'cmi.interactions',
                'objectives_count_element' => 'cmi.objectives._count',
                'objectives_prefix' => 'cmi.objectives'
            ]);
        } else {
            return array_merge($baseModel, [
                'location_element' => 'cmi.location',
                'completion_status_element' => 'cmi.completion_status',
                'success_status_element' => 'cmi.success_status',
                'score_scaled_element' => 'cmi.score.scaled',
                'score_raw_element' => 'cmi.score.raw',
                'score_min_element' => 'cmi.score.min',
                'score_max_element' => 'cmi.score.max',
                'entry_element' => 'cmi.entry',
                'exit_element' => 'cmi.exit',
                'interactions_count_element' => 'cmi.interactions._count',
                'interactions_prefix' => 'cmi.interactions',
                'objectives_count_element' => 'cmi.objectives._count',
                'objectives_prefix' => 'cmi.objectives',
                'progress_measure_element' => 'cmi.progress_measure'
            ]);
        }
    }

    /**
     * Extract SCOs data for database creation
     */
    private function extractScosData(ScormManifestDTO $manifest): array
    {
        return $manifest->getScoDataForDatabase();
    }

    /**
     * Determine the main entry point for the SCORM package
     */
    private function determineEntryPoint(ScormManifestDTO $manifest): string
    {
        $entryPoint = $manifest->getPrimaryLaunchUrl();

        if (!$entryPoint) {
            throw new ScormParsingException('Could not determine entry point for SCORM package');
        }

        return $entryPoint;
    }

    /**
     * Get session time element name for SCORM version
     */
    private function getSessionTimeElement(ScormVersionEnum $version): string
    {
        return $version === ScormVersionEnum::SCORM_12
            ? 'cmi.core.session_time'
            : 'cmi.session_time';
    }

    /**
     * Get total time element name for SCORM version
     */
    private function getTotalTimeElement(ScormVersionEnum $version): string
    {
        return $version === ScormVersionEnum::SCORM_12
            ? 'cmi.core.total_time'
            : 'cmi.total_time';
    }
}
