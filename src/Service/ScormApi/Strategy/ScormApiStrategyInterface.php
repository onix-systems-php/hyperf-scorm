<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi\Strategy;

/**
 * Strategy interface for different SCORM API versions.
 */
interface ScormApiStrategyInterface
{
    /**
     * Get API configuration for specific SCORM version.
     */
    public function getApiConfiguration(): array;

    /**
     * Get CMI data model mapping.
     */
    public function getCmiDataModel(): array;

    /**
     * Map tracking element to database field.
     */
    public function mapTrackingElement(string $element, string $value): array;

    /**
     * Validate SCORM data element.
     */
    public function validateElement(string $element, string $value): bool;

    /**
     * Get supported SCORM version.
     */
    public function getSupportedVersion(): string;
}
