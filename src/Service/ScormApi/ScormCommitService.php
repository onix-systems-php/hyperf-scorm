<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCommitDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCommitInteractionDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;

use function Hyperf\Collection\collect;
use function Hyperf\Support\now;

#[Service]
class ScormCommitService
{
    public const ACTION = 'compact_commit_scorm_data';

    public function __construct(
        private readonly ScormUserSessionRepository $sessionRepository,
    ) {}

    #[Transactional(attempts: 1)]
    public function run($packageId, ScormCommitDTO $scormCommitDTO): array
    {
        $session = $this->sessionRepository->findByIdentifier($packageId, $scormCommitDTO->session_token, true, true);
        $session->load('interactions');

        $this->updateSessionSummary($session, $scormCommitDTO);
        $this->createInteractions($session, $scormCommitDTO->interactions);

        // todo should  return dto not array
        return [
            'session_id' => $session->id,
            'student_id' => $scormCommitDTO->student_id,
            'session_token' => $session->session_token,
            'lesson_status' => $scormCommitDTO->lesson->status,
            'current_location' => $scormCommitDTO->lesson->location,
            'exit_mode' => $scormCommitDTO->lesson->exit,
            //            'entry' => $compactData->lesson->entry,
            //            'credit' => $compactData->lesson->credit,
            'score' => $scormCommitDTO->score,
            'score_percentage' => $scormCommitDTO->score_percentage,
            'interactions_count' => $scormCommitDTO->getInteractionsCount(),
            'session_time_seconds' => $scormCommitDTO->session->session_time,
            'suspend_data' => $scormCommitDTO->session->suspend_data,
            'session_time' => $scormCommitDTO->session->session_time,
            'total_time' => $scormCommitDTO->session->total_time,
            'is_completed' => $scormCommitDTO->isCompleted(),
            'is_passed' => $scormCommitDTO->isPassed(),
            'processed_at' => now()->toISOString(),
        ];
    }

    private function updateSessionSummary($session, ScormCommitDTO $scormCommitDTO): void
    {
        $updateData = [
            'last_activity_at' => now(),
            'student_name' => $scormCommitDTO->student_name,
            'student_id' => $scormCommitDTO->student_id,

            'lesson_status' => $scormCommitDTO->lesson->status,
            'lesson_location' => $scormCommitDTO->lesson->location,
            'lesson_entry' => $scormCommitDTO->lesson->entry,
            'lesson_credit' => $scormCommitDTO->lesson->credit,
            'lesson_mode' => $scormCommitDTO->lesson->mode,
            'lesson_exit' => $scormCommitDTO->lesson->exit,

            'exit_mode' => $scormCommitDTO->lesson->exit,

            'score_raw' => $scormCommitDTO->score,
            'session_time' => $scormCommitDTO->session->session_time,
            'total_time' => $scormCommitDTO->session->total_time,
            'interactions_count' => $scormCommitDTO->getInteractionsCount(),
            'score' => $scormCommitDTO->score,
            'score_percentage' => $scormCommitDTO->score_percentage,
            'session_time_seconds' => $scormCommitDTO->session->session_time,
            'suspend_data' => $scormCommitDTO->session->suspend_data,
            'is_completed' => $scormCommitDTO->isCompleted(),
            'is_passed' => $scormCommitDTO->isPassed(),
            'processed_at' => now()->toISOString(),
        ];

        if ($scormCommitDTO->isCompleted() && ! $session->completed_at) {
            $updateData['completed_at'] = $scormCommitDTO->getCompletedTimestamp()
                ? new \DateTime($scormCommitDTO->getCompletedTimestamp())
                : now();
        }

        $session = $this->sessionRepository->update($session, $updateData);
        $this->sessionRepository->save($session);
    }

    private function createInteractions(ScormSession $session, array $interactions): void
    {
        $existingIds = $session->interactions->pluck('interaction_id')->toArray();
        $data = collect($interactions)
            ->filter(fn (ScormCommitInteractionDTO $interaction) => ! in_array($interaction->id, $existingIds))
            ->map(function (ScormCommitInteractionDTO $interaction) {
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
}
