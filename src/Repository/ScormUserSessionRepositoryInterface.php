<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession;

/**
 * Repository interface for SCORM user sessions
 */
interface ScormUserSessionRepositoryInterface
{
    /**
     * Find session by ID
     */
    public function findById(string $id): ?\OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession;

    /**
     * Find active session for user and package
     */
    public function findActiveSession(int $packageId, int $userId): ?\OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession;

    /**
     * Find all sessions for user
     */
    public function findByUserId(int $userId): array;

    /**
     * Find all sessions for package
     */
    public function findByPackageId(int $packageId): array;

    /**
     * Save session (create or update)
     */
    public function save(\OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession $session): \OnixSystemsPHP\HyperfScorm\Entity\ScormUserSession;

    /**
     * Delete session
     */
    public function delete(string $sessionId): bool;

    /**
     * Update session suspend data
     */
    public function updateSuspendData(string $sessionId, array $suspendData): bool;

    /**
     * Update session status
     */
    public function updateStatus(string $sessionId, string $status): bool;

    /**
     * Get session statistics for package
     */
    public function getSessionStatistics(int $packageId): array;
}
