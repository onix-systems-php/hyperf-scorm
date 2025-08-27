<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Repository\ScormAttemptRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Model\ScormAttempt;
use OnixSystemsPHP\HyperfScorm\DTO\CmiDataDTO;
use Carbon\Carbon;

class ScormAttemptService
{
    public function __construct(
        private ScormAttemptRepositoryInterface $attemptRepository
    ) {}

    public function startAttempt(int $packageId, int $userId): ScormAttempt
    {
        $activeAttempt = $this->attemptRepository->findActiveAttempt($packageId, $userId);

        if ($activeAttempt) {
            return $activeAttempt;
        }

        $data = [
            'package_id' => $packageId,
            'user_id' => $userId,
            'status' => ScormAttempt::STATUS_INCOMPLETE,
            'lesson_status' => 'not attempted',
            'lesson_location' => '',
            'suspend_data' => '',
            'cmi_data' => new CmiDataDTO(
                lessonStatus: 'not attempted',
                entry: 'ab-initio',
                mode: 'normal',
                credit: 'credit',
                studentId: (string) $userId
            ),
            'started_at' => Carbon::now(),
            'score' => null,
            'time_spent' => 0,
        ];

        return $this->attemptRepository->create($data);
    }

    public function getAttempt(int $attemptId): ?ScormAttempt
    {
        return $this->attemptRepository->findById($attemptId);
    }

    public function suspendAttempt(int $attemptId, string $suspendData): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->suspend_data = $suspendData;
        $attempt->lesson_status = 'suspend';

        $this->attemptRepository->save($attempt);
        return true;
    }

    public function completeAttempt(int $attemptId, ?float $finalScore = null): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->status = ScormAttempt::STATUS_COMPLETED;
        $attempt->lesson_status = 'completed';
        $attempt->completed_at = Carbon::now();

        if ($finalScore !== null) {
            $attempt->score = $finalScore;
        }

        $this->attemptRepository->save($attempt);
        return true;
    }

    public function terminateAttempt(int $attemptId): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->completed_at = Carbon::now();
        $this->attemptRepository->save($attempt);
        return true;
    }

    public function updateLocation(int $attemptId, string $location): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->lesson_location = $location;
        $this->attemptRepository->save($attempt);
        return true;
    }

    public function updateScore(int $attemptId, float $score): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->score = $score;
        $attempt->setCmiValue('cmi.core.score.raw', $score);

        $this->attemptRepository->save($attempt);
        return true;
    }

    public function setCmiValue(int $attemptId, string $element, mixed $value): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->setCmiValue($element, $value);
        $this->attemptRepository->save($attempt);
        return true;
    }

    public function getCmiValue(int $attemptId, string $element): mixed
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        return $attempt?->getCmiValue($element);
    }

    public function getCmiDto(int $attemptId): ?CmiDataDTO
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        return $attempt?->getCmiDto();
    }

    public function setCmiDto(int $attemptId, CmiDataDTO $cmiDto): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->setCmiDto($cmiDto);
        $this->attemptRepository->save($attempt);
        return true;
    }

    public function setCmiValues(int $attemptId, array $cmiValues): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $attempt->setCmiValues($cmiValues);
        $this->attemptRepository->save($attempt);
        return true;
    }

    public function getCmiDataArray(int $attemptId): array
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        return $attempt?->getCmiDataArray() ?? [];
    }

    public function updateLessonStatus(int $attemptId, string $status): bool
    {
        return $this->setCmiValue($attemptId, 'cmi.core.lesson_status', $status);
    }

    public function updateLessonLocation(int $attemptId, string $location): bool
    {
        $this->updateLocation($attemptId, $location);
        return $this->setCmiValue($attemptId, 'cmi.core.lesson_location', $location);
    }

    public function addInteraction(int $attemptId, array $interaction): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            return false;
        }

        $cmiDto = $attempt->getCmiDto() ?? new CmiDataDTO();
        $interactions = $cmiDto->interactions;
        $interactions[] = array_merge($interaction, [
            'timestamp' => Carbon::now()->format('Y-m-d\TH:i:s')
        ]);
        $cmiDto->interactions = $interactions;

        $attempt->setCmiDto($cmiDto);
        $this->attemptRepository->save($attempt);
        return true;
    }
}
