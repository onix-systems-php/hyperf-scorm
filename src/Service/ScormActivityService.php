<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Repository\ScormActivityRepository;
use OnixSystemsPHP\HyperfScorm\Application\Service\ScormActivityServiceInterface;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Model\ScormActivity;
use OnixSystemsPHP\HyperfCore\Service\Service;
use Carbon\Carbon;

/**
 * SCORM Activity Service implementation
 */
#[Service]
class ScormActivityService implements ScormActivityServiceInterface
{
    public function __construct(
        private readonly ScormActivityRepository $scormActivityRepository,
        private readonly ScormUserSessionRepositoryInterface $sessionRepository
    ) {}

    public function recordQuestionAnswer(
        string $sessionId,
        string $questionId,
        string $answer,
        bool $isCorrect,
        ?float $score = null
    ): ScormActivity
    {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_QUESTION_ANSWER,
            'activity_data' => [
                'question_id' => $questionId,
                'answer' => $answer,
                'is_correct' => $isCorrect,
                'score' => $score,
                'timestamp' => Carbon::now()->toISOString()
            ],
            'scorm_element' => 'cmi.interactions.' . uniqid(),
            'scorm_value' => json_encode([
                'id' => $questionId,
                'student_response' => $answer,
                'result' => $isCorrect ? 'correct' : 'incorrect',
                'score' => $score
            ]),
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->scormActivityRepository->createActivity($activity);
    }

    public function recordLessonCompletion(
        string $sessionId,
        string $lessonId,
        float $completionPercentage,
        ?float $finalScore = null
    ): ScormActivity {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_LESSON_COMPLETE,
            'activity_data' => [
                'lesson_id' => $lessonId,
                'completion_percentage' => $completionPercentage,
                'final_score' => $finalScore,
                'timestamp' => Carbon::now()->toISOString()
            ],
            'scorm_element' => 'cmi.core.lesson_status',
            'scorm_value' => 'completed',
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    public function recordInteraction(
        string $sessionId,
        string $interactionType,
        array $interactionData
    ): ScormActivity {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activityData = array_merge($interactionData, [
            'interaction_type' => $interactionType,
            'timestamp' => Carbon::now()->toISOString()
        ]);

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_INTERACTION,
            'activity_data' => $activityData,
            'scorm_element' => 'cmi.interactions.' . ($interactionData['id'] ?? uniqid()),
            'scorm_value' => json_encode($interactionData),
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    public function recordLocationChange(
        string $sessionId,
        string $newLocation,
        ?string $previousLocation = null
    ): ScormActivity {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_LOCATION_CHANGE,
            'activity_data' => [
                'new_location' => $newLocation,
                'previous_location' => $previousLocation,
                'timestamp' => Carbon::now()->toISOString()
            ],
            'scorm_element' => 'cmi.core.lesson_location',
            'scorm_value' => $newLocation,
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    public function recordSessionStart(string $sessionId): ScormActivity
    {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activityData = [
            'event' => 'session_start',
            'timestamp' => Carbon::now()->toISOString()
        ];

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_SESSION_START,
            'activity_data' => $activityData,
            'scorm_element' => 'session.start',
            'scorm_value' => 'true',
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    public function recordSessionSuspend(string $sessionId, array $suspendData): ScormActivity
    {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activityData = [
            'event' => 'session_suspend',
            'suspend_data' => $suspendData,
            'timestamp' => Carbon::now()->toISOString()
        ];

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_SESSION_SUSPEND,
            'activity_data' => $activityData,
            'scorm_element' => 'cmi.suspend_data',
            'scorm_value' => json_encode($suspendData),
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    public function recordSessionTerminate(string $sessionId): ScormActivity
    {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activityData = [
            'event' => 'session_terminate',
            'timestamp' => Carbon::now()->toISOString()
        ];

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_SESSION_TERMINATE,
            'activity_data' => $activityData,
            'scorm_element' => 'session.terminate',
            'scorm_value' => 'true',
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    public function getUserProgress(int $userId, int $packageId): array
    {
        return $this->activityRepository->getUserAnalytics($userId, $packageId);
    }

    public function getSessionSummary(string $sessionId): array
    {
        return $this->activityRepository->getSessionProgress($sessionId);
    }

    public function getQuestionAnswers(string $sessionId): array
    {
        $activities = $this->activityRepository->getQuestionAnswers($sessionId);

        $answers = [];
        foreach ($activities as $activity) {
            $activityData = $activity->activity_data;
            $answers[] = [
                'question_id' => $activityData['question_id'] ?? null,
                'answer' => $activityData['answer'] ?? null,
                'is_correct' => $activityData['is_correct'] ?? null,
                'score' => $activityData['score'] ?? null,
                'timestamp' => $activity->activity_timestamp->toISOString(),
                'scorm_element' => $activity->scorm_element,
                'scorm_value' => $activity->scorm_value
            ];
        }

        return $answers;
    }

    public function calculateSessionScore(string $sessionId): float
    {
        $activities = $this->activityRepository->findBySession($sessionId);

        $totalScore = 0.0;
        $scoredActivities = 0;

        foreach ($activities as $activity) {
            $activityData = $activity->activity_data;
            if (isset($activityData['score'])) {
                $totalScore += (float) $activityData['score'];
                $scoredActivities++;
            }
        }

        return $scoredActivities > 0 ? $totalScore / $scoredActivities : 0.0;
    }

    public function getPackageAnalytics(int $packageId): array
    {
        return $this->activityRepository->getPackageAnalytics($packageId);
    }

    /**
     * Record score update activity
     */
    public function recordScoreUpdate(
        string $sessionId,
        float $score,
        ?float $previousScore = null
    ): ScormActivity {
        $session = $this->sessionRepository->findById($sessionId);
        if (!$session) {
            throw new \InvalidArgumentException("Session not found: {$sessionId}");
        }

        $activityData = [
            'score' => $score,
            'previous_score' => $previousScore,
            'timestamp' => Carbon::now()->toISOString()
        ];

        $activity = [
            'session_id' => $sessionId,
            'package_id' => $session->getPackageId(),
            'user_id' => $session->getUserId(),
            'activity_type' => ScormActivity::TYPE_SCORE_UPDATE,
            'activity_data' => $activityData,
            'scorm_element' => 'cmi.score.raw',
            'scorm_value' => (string) $score,
            'activity_timestamp' => Carbon::now()->format('Y-m-d H:i:s')
        ];

        return $this->activityRepository->createActivity($activity);
    }

    /**
     * Get activities by date range
     */
    public function getActivitiesByDateRange(
        int $packageId,
        Carbon $startDate,
        Carbon $endDate,
        ?int $userId = null
    ): array {
        return $this->activityRepository->findByDateRange($packageId, $startDate, $endDate, $userId);
    }

    /**
     * Get latest activity for session
     */
    public function getLatestActivity(string $sessionId): ?ScormActivity
    {
        return $this->activityRepository->getLatestActivity($sessionId);
    }

    /**
     * Clean up old activities
     */
    public function cleanupOldActivities(int $daysOld = 90): int
    {
        return $this->activityRepository->cleanupOldActivities($daysOld);
    }
}
