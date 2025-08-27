<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Entity\ScormActivity;

/**
 * @method \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity create(array $data)
 * @method \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity update(\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity $model, array $data)
 * @method \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity save(\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity $model)
 * @method bool delete(\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity $model)
 * @method \Hyperf\Database\Model\Builder|ScormActivityRepository finder(string $type, ...$parameters)
 * @method null|\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity fetchOne(bool $lock, bool $force)
 */
class ScormActivityRepository extends AbstractRepository
{
    protected string $modelClass = \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity::class;


    public function createActivity(array $data): \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity
    {
        $activity = new \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity();
        $activity->fill($data);
        $activity->save();

        return $activity;
    }

    public function findById(int $id, bool $lock = false, bool $force = false): ?\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity
    {
        return $this->finder('id', $id)->fetchOne($lock, $force);
    }

    public function scopeId($builder, int $id): void
    {
        $builder->where('id', '=', $id);
    }

    public function findBySession(string $sessionId): array
    {
        return $this->query()->where('session_id', $sessionId)
            ->orderBy('activity_timestamp', 'desc')
            ->get()
            ->toArray();
    }

    public function findByUserAndPackage(int $userId, int $packageId): array
    {
        return $this->query()->where('user_id', $userId)
            ->where('package_id', $packageId)
            ->orderBy('activity_timestamp', 'desc')
            ->get()
            ->toArray();
    }

    public function getQuestionAnswers(string $sessionId): array
    {
        return $this->query()->where('session_id', $sessionId)
            ->where('activity_type', \OnixSystemsPHP\HyperfScorm\Entity\ScormActivity::TYPE_QUESTION_ANSWER)
            ->orderBy('activity_timestamp')
            ->get()
            ->toArray();
    }

    public function getLatestActivity(string $sessionId): ?\OnixSystemsPHP\HyperfScorm\Entity\ScormActivity
    {
        return $this->query()->where('session_id', $sessionId)
            ->orderBy('activity_timestamp', 'desc')
            ->first();
    }

    public function deleteBySession(string $sessionId): bool
    {
        return $this->query()->where('session_id', $sessionId)->delete() > 0;
    }

    /**
     * Find activities by type
     */
    public function findByType(string $sessionId, string $activityType): array
    {
        return $this->query()->where('session_id', $sessionId)
            ->where('activity_type', $activityType)
            ->orderBy('activity_timestamp')
            ->get()
            ->toArray();
    }

    /**
     * Get session progress summary
     */
//    public function getSessionProgress(string $sessionId): array
//    {
//        $stats = $this->model::select([
//                Db::raw('COUNT(*) as total_activities'),
//                Db::raw('COUNT(CASE WHEN activity_type = "question_answer" THEN 1 END) as question_answers'),
//                Db::raw('COUNT(CASE WHEN activity_type = "question_answer" AND JSON_EXTRACT(activity_data, "$.is_correct") = true THEN 1 END) as correct_answers'),
//                Db::raw('COUNT(CASE WHEN activity_type = "lesson_complete" THEN 1 END) as lessons_completed'),
//                Db::raw('COUNT(CASE WHEN activity_type = "location_change" THEN 1 END) as location_changes'),
//                Db::raw('MIN(activity_timestamp) as first_activity'),
//                Db::raw('MAX(activity_timestamp) as last_activity')
//            ])
//            ->where('session_id', $sessionId)
//            ->first();
//
//        $correctAnswers = (int) $stats->correct_answers;
//        $totalQuestions = (int) $stats->question_answers;
//
//        return [
//            'session_id' => $sessionId,
//            'total_activities' => (int) $stats->total_activities,
//            'question_answers' => $totalQuestions,
//            'correct_answers' => $correctAnswers,
//            'accuracy_percentage' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0,
//            'lessons_completed' => (int) $stats->lessons_completed,
//            'location_changes' => (int) $stats->location_changes,
//            'first_activity' => $stats->first_activity,
//            'last_activity' => $stats->last_activity,
//            'session_duration' => $stats->first_activity && $stats->last_activity ?
//                Carbon::parse($stats->first_activity)->diffInSeconds(Carbon::parse($stats->last_activity)) : 0
//        ];
//    }

    /**
     * Get user learning analytics
     */
//    public function getUserAnalytics(int $userId, int $packageId): array
//    {
//        $stats = $this->model::select([
//                Db::raw('COUNT(DISTINCT session_id) as total_sessions'),
//                Db::raw('COUNT(*) as total_activities'),
//                Db::raw('COUNT(CASE WHEN activity_type = "question_answer" THEN 1 END) as total_questions'),
//                Db::raw('COUNT(CASE WHEN activity_type = "question_answer" AND JSON_EXTRACT(activity_data, "$.is_correct") = true THEN 1 END) as correct_answers'),
//                Db::raw('AVG(CASE WHEN activity_type = "question_answer" AND JSON_EXTRACT(activity_data, "$.score") IS NOT NULL THEN CAST(JSON_EXTRACT(activity_data, "$.score") AS DECIMAL) END) as avg_score'),
//                Db::raw('MIN(activity_timestamp) as first_activity'),
//                Db::raw('MAX(activity_timestamp) as last_activity')
//            ])
//            ->where('user_id', $userId)
//            ->where('package_id', $packageId)
//            ->first();
//
//        $totalQuestions = (int) $stats->total_questions;
//        $correctAnswers = (int) $stats->correct_answers;
//
//        return [
//            'user_id' => $userId,
//            'package_id' => $packageId,
//            'total_sessions' => (int) $stats->total_sessions,
//            'total_activities' => (int) $stats->total_activities,
//            'total_questions' => $totalQuestions,
//            'correct_answers' => $correctAnswers,
//            'accuracy_percentage' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0,
//            'average_score' => round((float) $stats->avg_score, 2),
//            'first_activity' => $stats->first_activity,
//            'last_activity' => $stats->last_activity,
//            'total_learning_time' => $stats->first_activity && $stats->last_activity ?
//                Carbon::parse($stats->first_activity)->diffInSeconds(Carbon::parse($stats->last_activity)) : 0
//        ];
//    }

    /**
     * Get package analytics
     */
//    public function getPackageAnalytics(int $packageId): array
//    {
//        $stats = $this->model::select([
//                Db::raw('COUNT(DISTINCT user_id) as unique_users'),
//                Db::raw('COUNT(DISTINCT session_id) as total_sessions'),
//                Db::raw('COUNT(*) as total_activities'),
//                Db::raw('COUNT(CASE WHEN activity_type = "question_answer" THEN 1 END) as total_questions'),
//                Db::raw('COUNT(CASE WHEN activity_type = "question_answer" AND JSON_EXTRACT(activity_data, "$.is_correct") = true THEN 1 END) as correct_answers'),
//                Db::raw('AVG(CASE WHEN activity_type = "question_answer" AND JSON_EXTRACT(activity_data, "$.score") IS NOT NULL THEN CAST(JSON_EXTRACT(activity_data, "$.score") AS DECIMAL) END) as avg_score')
//            ])
//            ->where('package_id', $packageId)
//            ->first();
//
//        // Get activity distribution
//        $activityTypes = $this->model::select(['activity_type', Db::raw('COUNT(*) as count')])
//            ->where('package_id', $packageId)
//            ->groupBy('activity_type')
//            ->get()
//            ->keyBy('activity_type');
//
//        $totalQuestions = (int) $stats->total_questions;
//        $correctAnswers = (int) $stats->correct_answers;
//
//        return [
//            'package_id' => $packageId,
//            'unique_users' => (int) $stats->unique_users,
//            'total_sessions' => (int) $stats->total_sessions,
//            'total_activities' => (int) $stats->total_activities,
//            'total_questions' => $totalQuestions,
//            'correct_answers' => $correctAnswers,
//            'accuracy_percentage' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0,
//            'average_score' => round((float) $stats->avg_score, 2),
//            'activity_distribution' => $activityTypes->toArray()
//        ];
//    }

    /**
     * Clean up old activities
     */
    public function cleanupOldActivities(int $daysOld = 90): int
    {
        return $this->query()->where('created_at', '<', now()->subDays($daysOld)->format('Y-m-d H:i:s'))
            ->delete();
    }
}
