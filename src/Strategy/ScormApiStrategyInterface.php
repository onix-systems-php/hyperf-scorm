<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Strategy;

/**
 * Strategy interface for different SCORM API versions
 */
interface ScormApiStrategyInterface
{
    /**
     * Get API configuration for specific SCORM version
     */
    public function getApiConfiguration(): array;

    /**
     * Get CMI data model mapping
     */
    public function getCmiDataModel(): array;

    /**
     * Map tracking element to database field
     */
    public function mapTrackingElement(string $element, string $value): array;

    /**
     * Validate SCORM data element
     */
    public function validateElement(string $element, string $value): bool;

    /**
     * Get supported SCORM version
     */
    public function getSupportedVersion(): string;
}
