<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use OnixSystemsPHP\HyperfCore\Model\Builder;
use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use function Hyperf\Support\now;

/**
 * @method ScormUserSession create(array $data)
 * @method ScormUserSession update(ScormUserSession $model, array $data)
 * @method ScormUserSession save(ScormUserSession $model)
 * @method bool delete(ScormUserSession $model)
 * @method Builder|ScormUserSessionRepository finder(string $type, ...$parameters)
 * @method null|ScormUserSession fetchOne(bool $lock, bool $force)
 */
class ScormUserSessionRepository extends AbstractRepository
{
    protected string $modelClass = ScormUserSession::class;

    public function findById(int $id, bool $lock = false, bool $force = false): ?ScormUserSession
    {
        return $this->finder('id', $id)->fetchOne($lock, $force);
    }

    public function findUserSessionForPackage(
        int $userId,
        int $packageId,
        bool $lock = false,
        bool $force = false
    ): ?ScormUserSession {
        return $this
            ->finder('user_id', $userId)
            ->finder('package_id', $packageId)
            ->fetchOne($lock, $force);
    }

    public function idScope(Builder $query, int $id): void
    {
        $query->where('id', '=', $id);
    }

    public function userIdScope(Builder $query, int $userId): void
    {
        $query->where('user_id', '=', $userId);
    }
    public function packageIdScope(Builder $query, int $packageId): void
    {
        $query->where('package_id', '=', $packageId);
    }


    public function findActiveSession(int $packageId, int $userId): ?ScormUserSession
    {
//        $data = Db::table($this->table)
//            ->where('package_id', $packageId)
//            ->where('user_id', $userId)
//            ->whereIn('status', [ScormUserSession::STATUS_ACTIVE, ScormUserSession::STATUS_SUSPENDED])
//            ->orderBy('last_accessed', 'desc')
//            ->first();

//        return $data ? $this->hydrate($data) : null;
        return null; // TODO: Implement findActiveSession() method.
    }

    public function findByUserId(int $userId): array
    {
        $results = Db::table($this->table)
            ->where('user_id', $userId)
            ->orderBy('last_accessed', 'desc')
            ->get();

        return $results->map(fn($data) => $this->hydrate($data))->toArray();
    }

    public function findByPackageId(int $packageId): array
    {
        $results = Db::table($this->table)
            ->where('package_id', $packageId)
            ->orderBy('last_accessed', 'desc')
            ->get();

        return $results->map(fn($data) => $this->hydrate($data))->toArray();
    }
    public function updateStatus(string $sessionId, string $status): bool
    {
        $updateData = [
            'status' => $status,
            'last_accessed' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ];

//        if ($status === ScormUserSession::STATUS_COMPLETED) {
//            $updateData['completed_at'] = now()->format('Y-m-d H:i:s');
//        }

        return Db::table($this->table)
            ->where('id', $sessionId)
            ->update($updateData) > 0;
    }

    public function getSessionStatistics(int $packageId): array
    {
        $stats = Db::table($this->table)
            ->select([
                Db::raw('COUNT(*) as total_sessions'),
                Db::raw('COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_sessions'),
                Db::raw('COUNT(CASE WHEN status = "active" THEN 1 END) as active_sessions'),
                Db::raw('COUNT(CASE WHEN status = "suspended" THEN 1 END) as suspended_sessions'),
                Db::raw('AVG(session_time) as avg_session_time'),
                Db::raw('AVG(score) as avg_score'),
            ])
            ->where('package_id', $packageId)
            ->first();

        return [
            'total_sessions' => (int)$stats->total_sessions,
            'completed_sessions' => (int)$stats->completed_sessions,
            'active_sessions' => (int)$stats->active_sessions,
            'suspended_sessions' => (int)$stats->suspended_sessions,
            'completion_rate' => $stats->total_sessions > 0 ?
                round($stats->completed_sessions / $stats->total_sessions * 100, 2) : 0,
            'avg_session_time' => (int)$stats->avg_session_time,
            'avg_score' => round((float)$stats->avg_score, 2),
        ];
    }

    /**
     * Hydrate database row into ScormUserSession model
     */
    private function hydrate($data): ScormUserSession
    {
        $session = new ScormUserSession();
        $session->id = $data->id;
        $session->package_id = $data->package_id;
        $session->user_id = $data->user_id;
        $session->status = $data->status;
        $session->suspend_data = json_decode($data->suspend_data ?? '[]', true);
        $session->lesson_location = $data->lesson_location;
        $session->lesson_status = $data->lesson_status;
        $session->score = $data->score;
        $session->session_time = $data->session_time;
        $session->started_at = $data->started_at ? Carbon::parse($data->started_at) : null;
        $session->last_accessed = $data->last_accessed ? Carbon::parse($data->last_accessed) : null;
        $session->completed_at = $data->completed_at ? Carbon::parse($data->completed_at) : null;
        $session->created_at = $data->created_at ? Carbon::parse($data->created_at) : null;
        $session->updated_at = $data->updated_at ? Carbon::parse($data->updated_at) : null;

        return $session;
    }
}
