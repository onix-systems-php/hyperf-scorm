<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\Database\Model\Model;
use OnixSystemsPHP\HyperfScorm\Model\ScormSco;

/**
 * Repository interface for SCORM SCOs
 */
interface ScormScoRepositoryInterface
{
    /**
     * Find SCO by ID
     */
    public function findById(int $id): ?ScormSco;

    /**
     * Find SCO by identifier and package ID
     */
    public function findByIdentifier(string $identifier, int $packageId): ?ScormSco;

    /**
     * Find SCOs by package ID
     */
    public function findByPackageId(int $packageId): array;

    /**
     * Save model (from AbstractRepository)
     * @deprecated Use saveSco() instead
     */
    public function save(Model $model): bool;

    /**
     * Save SCO
     */
    public function saveSco(ScormSco $sco): ScormSco;

    /**
     * Delete model (from AbstractRepository)
     * @deprecated Use deleteById() instead
     */
    public function delete(Model $model): bool;

    /**
     * Delete SCO by ID
     */
    public function deleteById(int $id): bool;

    /**
     * Create model (from AbstractRepository)
     * @deprecated Use createSco() instead
     */
    public function create(array $data = []): Model;

    /**
     * Create SCO
     */
    public function createSco(array $data): ScormSco;

    /**
     * Create multiple SCOs for package
     */
    public function createMultiple(int $packageId, array $scos): array;

    /**
     * Find SCOs with launch URLs
     */
    public function findLaunchableScos(int $packageId): array;

    /**
     * Find first SCO for package (default launch SCO)
     */
    public function findFirstSco(int $packageId): ?ScormSco;

    /**
     * Update SCO launch URL
     */
    public function updateLaunchUrl(int $id, string $launchUrl): bool;

    /**
     * Find SCOs by title pattern
     */
    public function findByTitlePattern(int $packageId, string $pattern): array;

    /**
     * Count SCOs for package
     */
    public function countByPackage(int $packageId): int;

    /**
     * Get SCO statistics for package
     */
    public function getPackageStatistics(int $packageId): array;

    /**
     * Delete all SCOs for package
     */
    public function deleteByPackage(int $packageId): bool;

    /**
     * Update SCO parameters
     */
    public function updateParameters(int $id, array $parameters): bool;

    /**
     * Find SCOs that have mastery score set
     */
    public function findScosWithMasteryScore(int $packageId): array;

    /**
     * Update SCO mastery score
     */
    public function updateMasteryScore(int $id, ?string $masteryScore): bool;
}
