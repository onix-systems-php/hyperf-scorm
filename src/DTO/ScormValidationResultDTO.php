<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for SCORM validation results
 */
class ScormValidationResultDTO extends AbstractDTO
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly ?ScormManifestDTO $manifestData = null
    ) {}

    /**
     * Create a valid result
     */
    public static function createValid(array $warnings = [], ?ScormManifestDTO $manifestData = null): self
    {
        return new self(
            isValid: true,
            errors: [],
            warnings: $warnings,
            manifestData: $manifestData
        );
    }

    /**
     * Create an invalid result
     */
    public static function createInvalid(array $errors, array $warnings = [], ?ScormManifestDTO $manifestData = null): self
    {
        return new self(
            isValid: false,
            errors: $errors,
            warnings: $warnings,
            manifestData: $manifestData
        );
    }

    /**
     * Get error count
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get warning count
     */
    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    /**
     * Get total issue count
     */
    public function getTotalIssueCount(): int
    {
        return $this->getErrorCount() + $this->getWarningCount();
    }

    /**
     * Check if there are any warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Check if there are any errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get validation summary
     */
    public function getSummary(): string
    {
        if ($this->isValid) {
            if ($this->hasWarnings()) {
                return "Valid with {$this->getWarningCount()} warning(s)";
            }
            return "Valid";
        }

        $summary = "Invalid with {$this->getErrorCount()} error(s)";
        if ($this->hasWarnings()) {
            $summary .= " and {$this->getWarningCount()} warning(s)";
        }

        return $summary;
    }

    /**
     * Get error summary string
     */
    public function getErrorSummary(): string
    {
        if (empty($this->errors)) {
            return '';
        }

        return implode('; ', $this->errors);
    }

    /**
     * Get warning summary string
     */
    public function getWarningSummary(): string
    {
        if (empty($this->warnings)) {
            return '';
        }

        return implode('; ', $this->warnings);
    }

    /**
     * Get all issues as formatted list
     */
    public function getFormattedIssues(): array
    {
        $issues = [];

        foreach ($this->errors as $error) {
            $issues[] = [
                'type' => 'error',
                'message' => $error,
                'severity' => 'high'
            ];
        }

        foreach ($this->warnings as $warning) {
            $issues[] = [
                'type' => 'warning',
                'message' => $warning,
                'severity' => 'medium'
            ];
        }

        return $issues;
    }

    /**
     * Get validation status for database storage
     */
    public function getStatus(): string
    {
        if ($this->isValid) {
            return $this->hasWarnings() ? 'valid_with_warnings' : 'valid';
        }

        return 'invalid';
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'error_count' => $this->getErrorCount(),
            'warning_count' => $this->getWarningCount(),
            'total_issue_count' => $this->getTotalIssueCount(),
            'summary' => $this->getSummary(),
            'status' => $this->getStatus(),
            'formatted_issues' => $this->getFormattedIssues()
        ];
    }
}
