<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\Database\Model\Model;
use OnixSystemsPHP\HyperfScorm\Entity\ScormActivity;

interface ScormActivityRepositoryInterface
{
    /**
     * Create model (from AbstractRepository)
     * @deprecated Use createActivity() instead
     */
    public function create(array $data = []): Model;

    /**
     * Create activity
     */
    public function createActivity(array $data): \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity;

    public function findById(int $id): ?\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity;

    public function findBySession(string $sessionId): array;

    public function findByUserAndPackage(int $userId, int $packageId): array;

    public function getQuestionAnswers(string $sessionId): array;

    public function getLatestActivity(string $sessionId): ?\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity;

    public function deleteBySession(string $sessionId): bool;
}
