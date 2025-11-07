<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\Database\Model\Model;
use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormSco;

/**
 * SCORM SCO Repository implementation.
 */
class ScormScoRepository extends AbstractRepository
{
    protected string $modelClass = ScormSco::class;

    public function findById(int $id): ?ScormSco
    {
        return $this->query()->find($id);
    }

    public function findByIdentifier(string $identifier, int $packageId): ?ScormSco
    {
        return $this->query()
            ->where('identifier', $identifier)
            ->where('package_id', $packageId)
            ->first();
    }

    public function findByPackageId(int $packageId): array
    {
        return $this->query()->where('package_id', $packageId)
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Implementation of AbstractRepository::save().
     */
    public function save(Model $model): bool
    {
        if (! $model instanceof ScormSco) {
            return false;
        }
        return $model->save();
    }

    /**
     * Save SCO.
     */
    public function saveSco(ScormSco $sco): ScormSco
    {
        $sco->save();
        return $sco;
    }

    /**
     * Implementation of AbstractRepository::delete().
     */
    public function delete(Model $model): bool
    {
        if (! $model instanceof ScormSco) {
            return false;
        }
        return $model->delete();
    }

    /**
     * Delete SCO by ID.
     */
    public function deleteById(int $id): bool
    {
        $sco = $this->findById($id);
        return $sco ? $sco->delete() : false;
    }

    /**
     * Implementation of AbstractRepository::create().
     */
    public function create(array $data = []): Model
    {
        $sco = new ScormSco();
        $sco->fill($data);
        $sco->save();

        return $sco;
    }

    /**
     * Create SCO.
     */
    public function createSco(array $data): ScormSco
    {
        $sco = new ScormSco();
        $sco->fill($data);
        $sco->save();

        return $sco;
    }

    public function createMultiple(int $packageId, array $scos): array
    {
        $createdScos = [];

        foreach ($scos as $scoData) {
            $scoData['package_id'] = $packageId;
            $createdScos[] = $this->createSco($scoData);
        }

        return $createdScos;
    }

    /**
     * Find SCOs with launch URLs.
     */
    public function findLaunchableScos(int $packageId): array
    {
        return $this->query()->where('package_id', $packageId)
            ->whereNotNull('launch_url')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Find first SCO for package (default launch SCO).
     */
    public function findFirstSco(int $packageId): ?ScormSco
    {
        return $this->query()->where('package_id', $packageId)
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Update SCO launch URL.
     */
    public function updateLaunchUrl(int $id, string $launchUrl): bool
    {
        $sco = $this->findById($id);
        if (! $sco) {
            return false;
        }

        $sco->launch_url = $launchUrl;
        $sco->save();

        return true;
    }

    /**
     * Find SCOs by title pattern.
     */
    public function findByTitlePattern(int $packageId, string $pattern): array
    {
        return $this->query()->where('package_id', $packageId)
            ->where('title', 'like', '%' . $pattern . '%')
            ->orderBy('title')
            ->get()
            ->toArray();
    }

    /**
     * Count SCOs for package.
     */
    public function countByPackage(int $packageId): int
    {
        return $this->query()->where('package_id', $packageId)->count();
    }

    /**
     * Get SCO statistics for package.
     */
    public function getPackageStatistics(int $packageId): array
    {
        $totalScos = $this->countByPackage($packageId);
        $launchableScos = $this->query()->where('package_id', $packageId)
            ->whereNotNull('launch_url')
            ->count();

        $scosWithPrerequisites = $this->query()->where('package_id', $packageId)
            ->whereNotNull('prerequisites')
            ->count();

        $scosWithMasteryScore = $this->query()->where('package_id', $packageId)
            ->whereNotNull('mastery_score')
            ->count();

        return [
            'total_scos' => $totalScos,
            'launchable_scos' => $launchableScos,
            'scos_with_prerequisites' => $scosWithPrerequisites,
            'scos_with_mastery_score' => $scosWithMasteryScore,
            'launchable_percentage' => $totalScos > 0
                ? round(($launchableScos / $totalScos) * 100, 2) : 0,
        ];
    }

    /**
     * Delete all SCOs for package.
     */
    public function deleteByPackage(int $packageId): bool
    {
        return $this->query()->where('package_id', $packageId)->delete() > 0;
    }

    /**
     * Update SCO parameters.
     */
    public function updateParameters(int $id, array $parameters): bool
    {
        $sco = $this->findById($id);
        if (! $sco) {
            return false;
        }

        $sco->parameters = $parameters;
        $sco->save();

        return true;
    }

    /**
     * Find SCOs that have mastery score set.
     */
    public function findScosWithMasteryScore(int $packageId): array
    {
        return $this->query()->where('package_id', $packageId)
            ->whereNotNull('mastery_score')
            ->orderBy('mastery_score', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Update SCO mastery score.
     */
    public function updateMasteryScore(int $id, ?string $masteryScore): bool
    {
        $sco = $this->findById($id);
        if (! $sco) {
            return false;
        }

        $sco->mastery_score = $masteryScore;
        $sco->save();

        return true;
    }
}
