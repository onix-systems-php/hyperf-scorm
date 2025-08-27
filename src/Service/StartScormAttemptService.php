<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Repository\ScormAttemptRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Model\ScormAttempt;
use OnixSystemsPHP\HyperfScorm\DTO\StartScormAttemptDTO;
use OnixSystemsPHP\HyperfScorm\DTO\CmiDataDTO;
use OnixSystemsPHP\HyperfScorm\Constants\AttemptStatuses;
use OnixSystemsPHP\HyperfScorm\Constants\CmiElements;
use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfCore\Service\Service;
use Carbon\Carbon;

/**
 * Service for starting SCORM attempts
 */
#[Service]
class StartScormAttemptService
{
    public function __construct(
        private readonly ScormAttemptRepositoryInterface $attemptRepository
    ) {}

    #[Transactional(attempts: 1)]
    public function run(StartScormAttemptDTO $dto): ScormAttempt
    {
        // Check for existing active attempt
        $existingAttempt = $this->attemptRepository->findActiveAttempt(
            $dto->packageId,
            $dto->userId
        );

        if ($existingAttempt) {
            return $existingAttempt;
        }

        // Create new attempt with initialized CMI data
        $data = [
            'package_id' => $dto->packageId,
            'user_id' => $dto->userId,
            'status' => AttemptStatuses::INCOMPLETE,
            'lesson_status' => CmiElements::STATUS_NOT_ATTEMPTED,
            'lesson_location' => '',
            'suspend_data' => '',
            'cmi_data' => new CmiDataDTO(
                lessonStatus: CmiElements::STATUS_NOT_ATTEMPTED,
                entry: CmiElements::ENTRY_AB_INITIO,
                mode: CmiElements::MODE_NORMAL,
                credit: CmiElements::CREDIT_CREDIT,
                studentId: (string) $dto->userId
            ),
            'started_at' => Carbon::now(),
            'score' => null,
            'time_spent' => 0,
        ];

        return $this->attemptRepository->create($data);
    }
}
