<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Query\Builder;
use Hyperf\DbConnection\Db;
use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormAttempt;

/**
 * SCORM Attempt Repository implementation
 */
class ScormAttemptRepository extends AbstractRepository implements ScormAttemptRepositoryInterface
{
    protected string $modelClass = \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::class;

    public function findById(int $id): ?ScormAttempt
    {
        return $this->query()->find($id);
    }

    public function findActiveAttempt(int $packageId, int $userId): ?ScormAttempt
    {
        return $this->query()->where('package_id', $packageId)
            ->where('user_id', $userId)
            ->whereNotIn('status', [\OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_COMPLETED, \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_PASSED, \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_FAILED])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function findByUserId(int $userId): array
    {
        return $this->query()->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function findByPackageId(int $packageId): array
    {
        return $this->query()->where('package_id', $packageId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Implementation of AbstractRepository::save()
     */
    public function save(Model $model): bool
    {
        if (!$model instanceof ScormAttempt) {
            return false;
        }
        return $model->save();
    }

    /**
     * Implementation of ScormAttemptRepositoryInterface::saveAttempt()
     */
    public function saveAttempt(ScormAttempt $attempt): ScormAttempt
    {
        $attempt->save();
        return $attempt;
    }

    /**
     * Implementation of AbstractRepository::delete()
     */
    public function delete(Model $model): bool
    {
        if (!$model instanceof ScormAttempt) {
            return false;
        }
        return $model->delete();
    }

    /**
     * Implementation of ScormAttemptRepositoryInterface::deleteById()
     */
    public function deleteById(int $id): bool
    {
        $attempt = $this->findById($id);
        return $attempt ? $attempt->delete() : false;
    }

    /**
     * Implementation of AbstractRepository::create()
     */
    public function create(array $data = []): Model
    {
        $attempt = new \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt();
        $attempt->fill($data);
        $attempt->save();

        return $attempt;
    }

    /**
     * Implementation of ScormAttemptRepositoryInterface::createAttempt()
     */
    public function createAttempt(array $data): ScormAttempt
    {
        $attempt = new \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt();
        $attempt->fill($data);
        $attempt->save();

        return $attempt;
    }

    public function updateCmiData(int $attemptId, array $cmiData): bool
    {
        $attempt = $this->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->setCmiValues($cmiData);
        $attempt->save();

        return true;
    }

    public function updateStatus(int $attemptId, string $status): bool
    {
        $attempt = $this->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->status = $status;

        // Set completed_at if status indicates completion
        if (in_array($status, [\OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_COMPLETED, \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_PASSED, \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_FAILED])) {
            $attempt->completed_at = now();
        }

        $attempt->save();
        return true;
    }

    public function getAttemptStatistics(int $packageId): array
    {
        $stats = $this->query()->select([
            Db::raw('COUNT(*) as total_attempts'),
            Db::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_attempts'),
            Db::raw('COUNT(CASE WHEN status = "passed" THEN 1 END) as passed_attempts'),
            Db::raw('COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_attempts'),
            Db::raw('COUNT(CASE WHEN status = "incomplete" THEN 1 END) as incomplete_attempts'),
            Db::raw('AVG(score) as average_score'),
            Db::raw('AVG(time_spent) as average_time_spent'),
            Db::raw('MAX(score) as max_score'),
            Db::raw('MIN(score) as min_score'),
        ])
        ->where('package_id', $packageId)
        ->first();

        // Get completion rate by status
        $statusDistribution = $this->query()->select(['status', Db::raw('COUNT(*) as count')])
            ->where('package_id', $packageId)
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'total_attempts' => (int)$stats->total_attempts,
            'completed_attempts' => (int)$stats->completed_attempts,
            'passed_attempts' => (int)$stats->passed_attempts,
            'failed_attempts' => (int)$stats->failed_attempts,
            'incomplete_attempts' => (int)$stats->incomplete_attempts,
            'completion_rate' => $stats->total_attempts > 0 ?
                round(($stats->completed_attempts + $stats->passed_attempts) / $stats->total_attempts * 100, 2) : 0,
            'pass_rate' => $stats->total_attempts > 0 ?
                round($stats->passed_attempts / $stats->total_attempts * 100, 2) : 0,
            'average_score' => round((float)$stats->average_score, 2),
            'average_time_spent' => (int)$stats->average_time_spent,
            'max_score' => (float)$stats->max_score,
            'min_score' => (float)$stats->min_score,
            'status_distribution' => $statusDistribution->toArray(),
        ];
    }

    /**
     * Find attempts with specific CMI value
     */
    public function findAttemptsByCmiElement(string $element, string $value): array
    {
        return $this->query()->whereJsonContains('cmi_data', [$element => $value])
            ->get()
            ->toArray();
    }

    /**
     * Get user's best attempt for package
     */
    public function findBestAttemptForUser(int $packageId, int $userId): ?ScormAttempt
    {
        return $this->query()->where('package_id', $packageId)
            ->where('user_id', $userId)
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Get recent attempts for package
     */
    public function findRecentAttempts(int $packageId, int $limit = 10): array
    {
        return $this->query()->where('package_id', $packageId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Count attempts by status
     */
    public function countByStatus(int $packageId, string $status): int
    {
        return $this->query()->where('package_id', $packageId)
            ->where('status', $status)
            ->count();
    }

    /**
     * Update attempt time spent
     */
    public function updateTimeSpent(int $attemptId, int $timeSpent): bool
    {
        $attempt = $this->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->time_spent += $timeSpent;
        $attempt->save();

        return true;
    }

    /**
     * Get attempts that need cleanup (abandoned sessions)
     */
    public function findAbandonedAttempts(int $hoursOld = 24): array
    {
        return $this->query()->where('status', \OnixSystemsPHP\HyperfScorm\Model\ScormAttempt::STATUS_INCOMPLETE)
            ->where('updated_at', '<', now()->subHours($hoursOld))
            ->get()
            ->toArray();
    }
}
