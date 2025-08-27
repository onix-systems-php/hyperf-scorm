<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\Database\Model\Model;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;

/**
 * Repository interface for SCORM packages following Repository pattern
 */
interface ScormPackageRepositoryInterface
{
    /**
     * Find package by ID
     */
    public function findById(int $id): ?ScormPackage;

    /**
     * Find package by identifier
     */
    public function findByIdentifier(string $identifier): ?ScormPackage;

    /**
     * Get all packages with optional filters
     */
    public function findAll(array $filters = [], int $limit = 50, int $offset = 0): array;

    /**
     * Save package (create or update)
     * @deprecated Use savePackage() instead
     */
    public function save(Model $model): bool;

    /**
     * Save package (create or update)
     */
    public function savePackage(ScormPackage $package): ScormPackage;

    /**
     * Delete model
     * @deprecated Use deleteById() instead
     */
    public function delete(Model $model): bool;

    /**
     * Delete package by ID
     */
    public function deleteById(int $id): bool;

    /**
     * Count packages with filters
     */
    public function count(array $filters = []): int;

    /**
     * Find packages by user ID
     */
    public function findByUserId(int $userId): array;

    /**
     * Create multiple SCOs for a package
     */
    public function createScos(ScormPackage $package, array $scosData): void;
}
