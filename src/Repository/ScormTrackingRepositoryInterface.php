<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

/**
 * Repository interface for SCORM tracking data
 */
interface ScormTrackingRepositoryInterface
{
    /**
     * Store tracking data for a session
     */
    public function storeTrackingData(
        int $packageId,
        string $sessionId,
        int $userId,
        string $elementName,
        string $elementValue
    ): bool;

    /**
     * Get tracking value for specific element
     */
    public function getTrackingValue(
        int $packageId,
        string $sessionId,
        int $userId,
        string $elementName
    ): ?string;

    /**
     * Get all tracking data for a session
     */
    public function getSessionTrackingData(string $sessionId): array;

    /**
     * Get tracking data for user and package
     */
    public function getUserPackageTrackingData(int $userId, int $packageId): array;

    /**
     * Commit any pending tracking data
     */
    public function commitPendingData(string $sessionId): bool;

    /**
     * Get tracking statistics for package
     */
    public function getPackageTrackingStatistics(int $packageId): array;

    /**
     * Delete tracking data for session
     */
    public function deleteSessionTrackingData(string $sessionId): bool;

    /**
     * Get interaction tracking data
     */
    public function getInteractionData(string $sessionId): array;

    /**
     * Store interaction tracking data
     */
    public function storeInteractionData(
        string $sessionId,
        string $interactionId,
        array $interactionData
    ): bool;
}
