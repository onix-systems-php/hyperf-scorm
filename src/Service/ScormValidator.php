<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormValidationResultDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

/**
 * Service for validating SCORM packages and manifests
 */
class ScormValidator
{
    /**
     * Validate SCORM manifest structure and content
     */
    public function validateManifest(ScormManifestDTO $manifest): ScormValidationResultDTO
    {
        $errors = [];
        $warnings = [];

        // Basic manifest validation
        if (empty($manifest->identifier)) {
            $errors[] = 'Manifest identifier is required';
        }

        if (empty($manifest->title)) {
            $errors[] = 'Manifest title is required';
        }

        if (empty($manifest->scos)) {
            $errors[] = 'At least one SCO is required';
        }

        // Version-specific validation
        if ($manifest->version === ScormVersionEnum::SCORM_12) {
            $this->validateScorm12Specific($manifest, $errors, $warnings);
        } else {
            $this->validateScorm2004Specific($manifest, $errors, $warnings);
        }

        // SCO validation
        $this->validateScoItems($manifest, $errors, $warnings);

        // Resource validation
        $this->validateResources($manifest, $errors, $warnings);

        return new ScormValidationResultDTO(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            manifestData: $manifest
        );
    }

    /**
     * Validate SCORM package structure (files and directories)
     */
    public function validatePackageStructure(string $packagePath, ScormManifestDTO $manifest): ScormValidationResultDTO
    {
        $errors = [];
        $warnings = [];

        // Check if imsmanifest.xml exists
        if (!file_exists($packagePath . '/imsmanifest.xml')) {
            $errors[] = 'imsmanifest.xml file is missing';
        }

        // Check if all referenced SCO files exist
        foreach ($manifest->scos as $sco) {
            // Extract just the file path without parameters
            $launchUrl = $sco->launch_url;
            $urlParts = parse_url($launchUrl);
            $filePath = $urlParts['path'] ?? $launchUrl;

            $resourcePath = $packagePath . '/' . ltrim($filePath, '/');

            if (!file_exists($resourcePath)) {
                $errors[] = "Referenced SCO file not found: {$filePath}";
            }
        }

        // Check for common SCORM files
        $this->validateCommonScormFiles($packagePath, $warnings);

        // Validate package size and structure
        $this->validatePackageSize($packagePath, $warnings);

        return new ScormValidationResultDTO(
            isValid: empty($errors),
            errors: $errors,
            warnings: $warnings,
            manifestData: $manifest
        );
    }

    /**
     * Validate SCORM 1.2 specific requirements
     */
    private function validateScorm12Specific(ScormManifestDTO $manifest, array &$errors, array &$warnings): void
    {
        // Check metadata schema
        if (!empty($manifest->metadata['schema'])) {
            if ($manifest->metadata['schema'] !== 'ADL SCORM') {
                $warnings[] = 'Schema should be "ADL SCORM" for SCORM 1.2';
            }
        }

        if (!empty($manifest->metadata['schemaversion'])) {
            if (!str_contains($manifest->metadata['schemaversion'], '1.2')) {
                $warnings[] = 'Schema version should contain "1.2" for SCORM 1.2';
            }
        }

        // Validate launch data format (SCORM 1.2 specific)
        foreach ($manifest->scos as $sco) {
            // SCORM 1.2 does not have explicit datafromlms in our SCO structure
            // This validation is kept for compatibility but may not be needed
        }
    }

    /**
     * Validate SCORM 2004 specific requirements
     */
    private function validateScorm2004Specific(ScormManifestDTO $manifest, array &$errors, array &$warnings): void
    {
        // Check metadata schema
        if (!empty($manifest->metadata['schema'])) {
            if ($manifest->metadata['schema'] !== 'ADL SCORM') {
                $warnings[] = 'Schema should be "ADL SCORM" for SCORM 2004';
            }
        }

        if (!empty($manifest->metadata['schemaversion'])) {
            if (!str_contains($manifest->metadata['schemaversion'], '2004')) {
                $warnings[] = 'Schema version should contain "2004" for SCORM 2004';
            }
        }

        // Validate sequencing information if present (not implemented in new SCO approach)
        // Sequencing validation would need to be implemented separately if needed
    }

    /**
     * Validate SCO items
     */
    private function validateScoItems(ScormManifestDTO $manifest, array &$errors, array &$warnings): void
    {
        if (empty($manifest->scos)) {
            $errors[] = 'No SCO items found in manifest';
            return;
        }

        foreach ($manifest->scos as $sco) {
            // Check required fields
            if (empty($sco->identifier)) {
                $errors[] = 'SCO identifier is required';
            }

            if (empty($sco->launch_url)) {
                $errors[] = "SCO '{$sco->identifier}' must have a launch URL";
            }

            // Validate mastery score if present
            if ($sco->mastery_score !== null) {
                if ($sco->mastery_score < 0 || $sco->mastery_score > 1) {
                    $warnings[] = "Mastery score should be between 0-1 for SCO: {$sco->identifier}";
                }
            }

            // Validate time limit if present
            if (!empty($sco->max_time_allowed)) {
                if (!$this->isValidTimeInterval($sco->max_time_allowed)) {
                    $warnings[] = "Invalid time format for max_time_allowed in SCO: {$sco->identifier}";
                }
            }
        }
    }

    /**
     * Validate resources (now integrated into SCO validation)
     */
    private function validateResources(ScormManifestDTO $manifest, array &$errors, array &$warnings): void
    {
        foreach ($manifest->scos as $sco) {
            if (empty($sco->identifier)) {
                $errors[] = 'SCO identifier is required';
            }

            if (empty($sco->launch_url)) {
                $errors[] = "SCO '{$sco->identifier}' must have a launch URL";
            }

            if (empty($sco->type)) {
                $warnings[] = "SCO '{$sco->identifier}' should have a type specified";
            }

            // Validate SCO resource type
            if (!empty($sco->type) && $sco->type === 'webcontent') {
                if (!$this->isValidWebContentType($sco->launch_url)) {
                    $warnings[] = "SCO '{$sco->identifier}' may not be valid web content";
                }
            }
        }
    }

    /**
     * Validate common SCORM files presence
     */
    private function validateCommonScormFiles(string $packagePath, array &$warnings): void
    {
        $commonFiles = [
            'adlcp_rootv1p2.xsd',
            'ims_xml.xsd',
            'imscp_rootv1p1p2.xsd',
            'imsmd_rootv1p2p1.xsd'
        ];

        foreach ($commonFiles as $file) {
            if (!file_exists($packagePath . '/' . $file)) {
                $warnings[] = "Common SCORM file missing: {$file}";
            }
        }
    }

    /**
     * Validate package size
     */
    private function validatePackageSize(string $packagePath, array &$warnings): void
    {
        $size = $this->getDirectorySize($packagePath);

        // Warn if package is larger than 100MB
        if ($size > 100 * 1024 * 1024) {
            $warnings[] = 'Package size exceeds 100MB, consider optimization';
        }

        // Count files
        $fileCount = $this->countFiles($packagePath);
        if ($fileCount > 1000) {
            $warnings[] = "Package contains {$fileCount} files, consider consolidation";
        }
    }

    /**
     * Validate sequencing rules (SCORM 2004)
     */
    private function validateSequencing(array $sequencing, array &$warnings): void
    {
        // Basic sequencing validation
        if (!empty($sequencing['controlMode'])) {
            $validModes = ['flow', 'forwardOnly', 'choice', 'choiceExit'];
            if (!in_array($sequencing['controlMode'], $validModes)) {
                $warnings[] = 'Invalid sequencing control mode';
            }
        }

        // Validate objectives if present
        if (!empty($sequencing['objectives'])) {
            foreach ($sequencing['objectives'] as $objective) {
                if (empty($objective['objectiveID'])) {
                    $warnings[] = 'Sequencing objective must have an ID';
                }
            }
        }
    }

    /**
     * Check if time interval is valid ISO 8601 format
     */
    private function isValidTimeInterval(string $timeInterval): bool
    {
        try {
            new \DateInterval($timeInterval);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if resource is valid web content type
     */
    private function isValidWebContentType(string $href): bool
    {
        $extension = strtolower(pathinfo($href, PATHINFO_EXTENSION));
        $validExtensions = ['html', 'htm', 'xhtml', 'xml'];

        return in_array($extension, $validExtensions);
    }

    /**
     * Get directory size in bytes
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Count files in directory
     */
    private function countFiles(string $directory): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }
}
