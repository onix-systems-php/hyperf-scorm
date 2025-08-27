<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

/**
 * Interface for SCORM tracking service
 */
interface ScormTrackingServiceInterface
{
    /**
     * Initialize SCORM session
     */
    public function initializeSession(string $sessionId): bool;

    /**
     * Set SCORM tracking value
     */
    public function setValue(string $sessionId, string $element, string $value): bool;

    /**
     * Get SCORM tracking value
     */
    public function getValue(string $sessionId, string $element): ?string;

    /**
     * Commit session data to database
     */
    public function commitSession(string $sessionId): bool;

    /**
     * Terminate SCORM session
     */
    public function terminateSession(string $sessionId): bool;

    /**
     * Get all tracking data for session
     */
    public function getSessionTrackingData(string $sessionId): array;

    /**
     * Suspend session with current state
     */
    public function suspendSession(string $sessionId, array $suspendData): bool;
}
