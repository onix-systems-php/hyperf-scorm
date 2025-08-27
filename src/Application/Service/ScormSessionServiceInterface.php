<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Application\Service;

use OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession;

/**
 * Application Service Interface for SCORM session management
 */
interface ScormSessionServiceInterface
{
    /**
     * Start new learning session or resume existing one
     */
    public function startOrResumeSession(int $packageId, int $userId): ScormUserSession;

    /**
     * Get current session for user and package
     */
    public function getCurrentSession(int $packageId, int $userId): ?ScormUserSession;

    /**
     * Suspend session with current state
     */
    public function suspendSession(string $sessionId, array $suspendData): bool;

    /**
     * Resume suspended session
     */
    public function resumeSession(string $sessionId): bool;

    /**
     * Complete session
     */
    public function completeSession(string $sessionId, ?float $finalScore = null): bool;

    /**
     * Terminate session
     */
    public function terminateSession(string $sessionId): bool;

    /**
     * Update session location
     */
    public function updateLocation(string $sessionId, string $newLocation): bool;

    /**
     * Get session restore data
     */
    public function getSessionRestoreData(string $sessionId): array;

    /**
     * Check if user can resume session
     */
    public function canResumeSession(string $sessionId): bool;

    /**
     * Get session statistics
     */
    public function getSessionStatistics(string $sessionId): array;
}
