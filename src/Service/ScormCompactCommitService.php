<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCompactCommitDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormSessionInteractionCommitDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormInteractionRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormTrackingRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormTrackingRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use function Hyperf\Collection\collect;
use function Hyperf\Support\now;

/**
 * SCORM Compact Commit Service - handles compact format commit operations
 */
#[Service]
class ScormCompactCommitService
{
    public const ACTION = 'compact_commit_scorm_data';

    public function __construct(
        private readonly ScormUserSessionRepository $sessionRepository,
        private readonly ScormInteractionRepository $scormInteractionRepository,
        //        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Process compact commit data
     */
    #[Transactional(attempts: 1)]
    public function commit(int $sessionId, ScormCompactCommitDTO $compactData): array
    {
        // Find the session
        $session = $this->sessionRepository->findById($sessionId, true, true);
        $session->load('interactions');

        xdebug_break();
        if (!$this->canCommitToSession($session)) {
            throw new \RuntimeException("Session cannot accept commits: {$sessionId}");
        }

        // Convert compact format to CMI structure
//        $cmiData = $compactData->toCmiStructure();

        // Update session summary data
        $this->updateSessionSummary($session, $compactData);
        $this->createInteractions($session, $compactData->interactions);
        // Store detailed tracking data
//        $this->storeTrackingData($session, $cmiData, $compactData);


        return [
            'session_id' => $sessionId,
            'student_id' => $compactData->student_id,
            'lesson_status' => $compactData->lesson->status,
            'current_location' => $compactData->lesson->location,
            'exit_mode' => $compactData->lesson->exit,
//            'entry' => $compactData->lesson->entry,
//            'credit' => $compactData->lesson->credit,
            'score' => $compactData->score,
            'score_percentage' => $compactData->score_percentage,
            'interactions_count' => $compactData->getInteractionsCount(),
            'session_time_seconds' => $compactData->session->session_time,
            'suspend_data' => $compactData->session->suspend_data,
            'session_time' => $compactData->session->session_time,
            'total_time' => $compactData->session->total_time,
            'is_completed' => $compactData->isCompleted(),
            'is_passed' => $compactData->isPassed(),
            'processed_at' => now()->toISOString(),
        ];
    }

    /**
     * Check if session can accept commits
     */
    private function canCommitToSession($session): bool
    {
        // Add your business logic here
        // For example, check if session is not terminated, not expired, etc.
        return true;
    }

    private function updateSessionSummary($session, ScormCompactCommitDTO $compactData): void
    {
        xdebug_break();

        $updateData = [
            'last_activity_at' => now(),
            'student_name' => $compactData->student_name,
            'student_id' => $compactData->student_id,

            'lesson_status' => $compactData->lesson->status,
            'current_location' => $compactData->lesson->location,
            'exit_mode' => $compactData->lesson->exit,

            'score_raw' => $compactData->score,
            'session_time' => $compactData->session->session_time,
            'total_time' => $compactData->session->total_time,
            'interactions_count' => $compactData->getInteractionsCount(),
            'score' => $compactData->score,
            'score_percentage' => $compactData->score_percentage,
            'session_time_seconds' => $compactData->session->session_time,
            'suspend_data' => $compactData->session->suspend_data,
            'is_completed' => $compactData->isCompleted(),
            'is_passed' => $compactData->isPassed(),
            'processed_at' => now()->toISOString(),
//            'updated_at' => now(),
        ];

        if ($compactData->isCompleted() && !$session->completed_at) {
            $updateData['completed_at'] = $compactData->getCompletedTimestamp()
                ? new \DateTime($compactData->getCompletedTimestamp())
                : now();
        }

//        if ($compactData->session->sessionTime > 0) {
//            $updateData['total_time'] = ($session->total_time ?? 0) + $compactData->session->sessionTime;
//        }

        xdebug_break();

        $session = $this->sessionRepository->update($session, $updateData);
        $this->sessionRepository->save($session);
    }

    private function createInteractions(ScormUserSession $session, array $interactions): void
    {
        xdebug_break();

        $existingIds = $session->interactions->pluck('interaction_id')->toArray();
        $data = collect((array)$interactions)
            ->filter(fn(ScormSessionInteractionCommitDTO $interaction) => !in_array($interaction->id, $existingIds))
            ->map(function (ScormSessionInteractionCommitDTO $interaction) use ($session) {

                return [
                    'interaction_id' => $interaction->id,
                    'type' => $interaction->type ?? 'choice',
                    'description' => $interaction->description ?? '',
                    'learner_response' => $interaction->learner_response,
                    'correct_response' => $interaction->correct_response,
                    'result' => $interaction->result ?? 'neutral',
                    'weighting' => $interaction->weighting,
                    'latency_seconds' => $interaction->latency_seconds,
                    'interaction_timestamp' => $interaction->timestamp ?? now()->toISOString(),
                    'objectives' => $interaction->objectives,
                    'created_at' => now(),
                ];
            })->toArray();

        $this->sessionRepository->createMany($session, $data, 'interactions');
    }

    /**
     * Store detailed tracking data
     */
//    private function storeTrackingData($session, array $cmiData, ScormCompactCommitDTO $compactData): void
//    {
//        // Store each CMI element using existing tracking service
//        foreach ($cmiData as $element => $value) {
//            if ($value !== '' && $value !== null) {
//                $this->trackingRepository->storeTrackingData(
//                    $session->package_id,
//                    $session->id,
//                    $session->user_id,
//                    $element,
//                    (string)$value
//                );
//            }
//        }
//
//        // Store interactions separately for better querying
//        $this->storeInteractionsData($session, $compactData->interactions);
//    }

    /**
     * Store interactions data in structured format
     */
//    private function storeInteractionsData($session, array $interactions): void
//    {
//        foreach ($interactions as $index => $interaction) {
//            // Store each interaction with its metadata
//            $interactionData = [
//                'session_id' => $session->id,
//                'user_id' => $session->user_id,
//                'package_id' => $session->package_id,
//                'interaction_index' => $index,
//                'interaction_id' => $interaction['id'] ?? '',
//                'interaction_type' => $interaction['type'] ?? 'choice',
//                'description' => $interaction['description'] ?? '',
//                'learner_response' => json_encode($interaction['learnerResponse'] ?? []),
//                'correct_response' => json_encode($interaction['correctResponse'] ?? []),
//                'result' => $interaction['result'] ?? 'neutral',
//                'weighting' => $interaction['weighting'] ?? null,
//                'latency_seconds' => $interaction['latency'] ?? null,
//                'timestamp' => $interaction['timestamp'] ?? now()->toISOString(),
//                'objectives' => json_encode($interaction['objectives'] ?? []),
//                'created_at' => now(),
//            ];
//
//            // Store using tracking repository or create specialized method
//            $this->storeInteractionRecord($interactionData);
//        }
//    }

    /**
     * Store individual interaction record
     */
//    private function storeInteractionRecord(array $interactionData): void
//    {
//        // You might want to create a dedicated interactions table and repository
//        // For now, store as tracking data with special prefix
//        $this->trackingRepository->storeTrackingData(
//            $interactionData['package_id'],
//            $interactionData['session_id'],
//            $interactionData['user_id'],
//            "interaction.{$interactionData['interaction_index']}.data",
//            json_encode($interactionData)
//        );
//    }

    /**
     * Dispatch commit event
     */
//    private function dispatchCommitEvent($session, ScormCompactCommitDTO $compactData): void
//    {
//        $event = new \OnixSystemsPHP\HyperfCore\Event\Action([
//            'action' => self::ACTION,
//            'user_id' => $session->user_id,
//            'package_id' => $session->package_id,
//            'session_id' => $session->id,
//            'data' => [
//                'student_id' => $compactData->studentId,
//                'lesson_status' => $compactData->lessonStatus,
//                'score' => $compactData->score,
//                'score_percentage' => $compactData->scorePercentage,
//                'interactions_count' => $compactData->getInteractionsCount(),
//                'session_time_seconds' => $compactData->sessionTime,
//                'is_completed' => $compactData->isCompleted(),
//                'is_passed' => $compactData->isPassed(),
//                'completed_at' => $compactData->getCompletedTimestamp(),
//            ],
//        ]);
//
////        $this->eventDispatcher->dispatch($event);
//    }

    /**
     * Get session restore data in compact format
     */
//    public function getSessionCompactData(int $sessionId): ?array
//    {
//        $session = $this->sessionRepository->findById($sessionId);
//        if (!$session) {
//            return null;
//        }
//
//        // Get latest tracking data
//        $trackingData = $this->trackingRepository->getSessionTrackingData($session->id);
//
//        // Convert to compact format
//        return [
//            'studentId' => $session->student_id ?? 'Guest',
//            'lessonStatus' => $session->lesson_status ?? 'not_attempted',
//            'score' => $session->score ?? 0,
//            'scorePercentage' => $this->calculateScorePercentage($session),
//            'sessionTime' => $session->total_time ?? 0,
//            'interactions' => $this->extractInteractionsFromTracking($trackingData),
//            'completedAt' => $session->completed_at?->toISOString(),
//        ];
//    }

    /**
     * Calculate score percentage from session data
     */
//    private function calculateScorePercentage($session): int
//    {
//        if (!$session->score) {
//            return 0;
//        }
//
//        // Assuming max score is 100, adjust as needed
//        return min(100, max(0, (int)round($session->score / 100 * 100)));
//    }

    /**
     * Extract interactions from tracking data
     */
//    private function extractInteractionsFromTracking(array $trackingData): array
//    {
//        $interactions = [];
//
//        // Extract interaction data from tracking records
//        foreach ($trackingData as $record) {
//            if (
//                strpos($record['element_name'], 'interaction.') === 0 &&
//                strpos($record['element_name'], '.data') !== false
//            ) {
//                $interactionData = json_decode($record['element_value'], true);
//                if ($interactionData) {
//                    $interactions[] = [
//                        'id' => $interactionData['interaction_id'],
//                        'type' => $interactionData['interaction_type'],
//                        'description' => $interactionData['description'],
//                        'learnerResponse' => json_decode($interactionData['learner_response'], true),
//                        'correctResponse' => json_decode($interactionData['correct_response'], true),
//                        'result' => $interactionData['result'],
//                        'weighting' => $interactionData['weighting'],
//                        'latency' => $interactionData['latency_seconds'],
//                        'timestamp' => $interactionData['timestamp'],
//                        'objectives' => json_decode($interactionData['objectives'], true),
//                    ];
//                }
//            }
//        }
//
//        return $interactions;
//    }
}
