<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\Database\Model\Model;
use OnixSystemsPHP\HyperfScorm\Model\ScormAttempt;

/**
 * Repository interface for SCORM attempts
 */
interface ScormAttemptRepositoryInterface
{
    /**
     * Find attempt by ID
     */
    public function findById(int $id): ?ScormAttempt;

    /**
     * Find active attempt for user and package
     */
    public function findActiveAttempt(int $packageId, int $userId): ?ScormAttempt;

    /**
     * Find attempts by user ID
     */
    public function findByUserId(int $userId): array;

    /**
     * Find attempts by package ID
     */
    public function findByPackageId(int $packageId): array;

    /**
     * Save model (from AbstractRepository)
     * @deprecated Use saveAttempt() instead
     */
    public function save(Model $model): bool;

    /**
     * Save attempt
     */
    public function saveAttempt(ScormAttempt $attempt): ScormAttempt;

    /**
     * Delete model (from AbstractRepository)
     * @deprecated Use deleteById() instead
     */
    public function delete(Model $model): bool;

    /**
     * Delete attempt by ID
     */
    public function deleteById(int $id): bool;

    /**
     * Create model (from AbstractRepository)
     * @deprecated Use createAttempt() instead
     */
    public function create(array $data = []): Model;

    /**
     * Create attempt
     */
    public function createAttempt(array $data): ScormAttempt;

    /**
     * Update CMI data for attempt
     */
    public function updateCmiData(int $attemptId, array $cmiData): bool;

    /**
     * Update attempt status
     */
    public function updateStatus(int $attemptId, string $status): bool;

    /**
     * Get attempt statistics for package
     */
    public function getAttemptStatistics(int $packageId): array;

    /**
     * Find attempts with specific CMI value
     */
    public function findAttemptsByCmiElement(string $element, string $value): array;

    /**
     * Get user's best attempt for package
     */
    public function findBestAttemptForUser(int $packageId, int $userId): ?ScormAttempt;

    /**
     * Get recent attempts for package
     */
    public function findRecentAttempts(int $packageId, int $limit = 10): array;

    /**
     * Count attempts by status
     */
    public function countByStatus(int $packageId, string $status): int;

    /**
     * Update attempt time spent
     */
    public function updateTimeSpent(int $attemptId, int $timeSpent): bool;

    /**
     * Get attempts that need cleanup (abandoned sessions)
     */
    public function findAbandonedAttempts(int $hoursOld = 24): array;
}
