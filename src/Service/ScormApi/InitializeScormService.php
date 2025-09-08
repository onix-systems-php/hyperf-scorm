<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service\ScormApi;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCompactCommitDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Repository\ScormUserSessionRepository;
use OnixSystemsPHP\HyperfScorm\Service\ScormPlayerService;

#[Service]
class InitializeScormService
{
    public const ACTION = 'initialize_scorm';

    public function __construct(
        public readonly ScormPackageRepository $scormPackageRepository,
        public readonly ScormUserSessionRepository $scormUserSessionRepository,
        public readonly ScormPlayerService $scormPlayerService,
    ) {
    }

    public function run(int $sessionId): ScormUserSession
    {
        //todo create session_id hash uuid, but if int you cant hack others sessions
        xdebug_break();

        $session = $this->scormUserSessionRepository->findById($sessionId);
        $session->load(['interactions']);
//        $scormCompactCommitDTO = ScormCompactCommitDTO::make([
//            'student_name' => $session->student_name,
//            'student_id' => $session->student_id ?? 2, //todo only fron database
//            'score' => $session->score_raw,
//            'score_percentage' => $session->score_percentage ?? 0, //todo calculate if null
//            'completed_at' => $session->completed_at,
//            'session' => [
//                'session_time' => $session->session_time,
//                'total_time' => $session->total_time,
//                'suspend_data' => $session->suspend_data,
//                'session_time_seconds' => $session->session_time_seconds,
//            ],
//            'lesson' => [
//                'status' => $session->lesson_status,
//                'location' => $session->current_location,
//                'exit' => $session->exit_mode,
//            ],
////            'interactions' => $session->interactions ?? [],//todo need this data or not? check can you go back in scorm
//            'interactions' => [],//todo need this data or not? check can you go back in scorm
//        ]);

        return $session;
    }
}
