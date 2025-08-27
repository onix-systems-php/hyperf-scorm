<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Entity;

use Carbon\Carbon;
use OnixSystemsPHP\HyperfScorm\Enum\ScormSessionStatusEnum;

/**
 * SCORM User Session Entity - represents a user's learning session
 * Updated to use simplified structure with JSONB activity tracking
 */
class ScormUserSession
{
    public string $id;
    private int $packageId;
    private int $userId;
    public ScormSessionStatusEnum $status;
    private array $suspendData;
    private ?array $activityLog;     // JSONB - all user activities
    private ?array $cmiData;         // JSONB - current CMI state
    private ?string $currentLocation;
    private ?float $scoreRaw;        // Current raw score
    private ?float $scoreScaled;     // Current scaled score (SCORM 2004)
    private int $totalTimeSeconds;   // Total time spent
    private ?Carbon $startedAt;
    private ?Carbon $lastAccessed;
    private ?Carbon $completedAt;
    private Carbon $createdAt;
    private Carbon $updatedAt;

    public function __construct(
        string $id,
        int $packageId,
        int $userId,
        ScormSessionStatusEnum $status = ScormSessionStatusEnum::ACTIVE,
        array $suspendData = [],
        ?array $activityLog = null,
        ?array $cmiData = null,
        ?string $currentLocation = null,
        ?float $scoreRaw = null,
        ?float $scoreScaled = null,
        int $totalTimeSeconds = 0,
        ?Carbon $startedAt = null,
        ?Carbon $lastAccessed = null,
        ?Carbon $completedAt = null,
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null
    ) {
        $this->id = $id;
        $this->packageId = $packageId;
        $this->userId = $userId;
        $this->status = $status;
        $this->suspendData = $suspendData;
        $this->activityLog = $activityLog ?? [];
        $this->cmiData = $cmiData ?? [];
        $this->currentLocation = $currentLocation;
        $this->scoreRaw = $scoreRaw;
        $this->scoreScaled = $scoreScaled;
        $this->totalTimeSeconds = $totalTimeSeconds;
        $this->startedAt = $startedAt ?? Carbon::now();
        $this->lastAccessed = $lastAccessed ?? Carbon::now();
        $this->completedAt = $completedAt;
        $this->createdAt = $createdAt ?? Carbon::now();
        $this->updatedAt = $updatedAt ?? Carbon::now();
    }

    /**
     * Create session from array data
     */
    public static function create(array $data): self
    {
        return new self(
            id: $data['id'],
            packageId: $data['package_id'],
            userId: $data['user_id'],
            status: $data['status'] ?? ScormSessionStatusEnum::ACTIVE,
            suspendData: $data['suspend_data'] ?? [],
            currentLocation: $data['current_location'] ?? null,
            scoreRaw: $data['score_raw'] ?? null,
            scoreScaled: $data['score_scaled'] ?? null,
            totalTimeSeconds: $data['total_time_seconds'] ?? 0,
            startedAt: $data['started_at'] ?? null,
            lastAccessed: $data['last_accessed'] ?? null,
            completedAt: $data['completed_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getPackageId(): int
    {
        return $this->packageId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getStatus(): ScormSessionStatusEnum
    {
        return $this->status;
    }

    public function getSuspendData(): array
    {
        return $this->suspendData;
    }

    public function getCurrentLocation(): ?string
    {
        return $this->currentLocation;
    }

    public function getActivityLog(): ?array
    {
        return $this->activityLog;
    }

    public function getCmiData(): ?array
    {
        return $this->cmiData;
    }

    public function getScoreRaw(): ?float
    {
        return $this->scoreRaw;
    }

    public function getScoreScaled(): ?float
    {
        return $this->scoreScaled;
    }

    public function getTotalTimeSeconds(): int
    {
        return $this->totalTimeSeconds;
    }

    public function getStartedAt(): ?Carbon
    {
        return $this->startedAt;
    }

    public function getLastAccessed(): ?Carbon
    {
        return $this->lastAccessed;
    }

    public function getCompletedAt(): ?Carbon
    {
        return $this->completedAt;
    }

    public function getCreatedAt(): Carbon
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): Carbon
    {
        return $this->updatedAt;
    }

    // Business methods
    public function isActive(): bool
    {
        return $this->status === ScormSessionStatusEnum::ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === ScormSessionStatusEnum::SUSPENDED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ScormSessionStatusEnum::COMPLETED;
    }

    public function isTerminated(): bool
    {
        return $this->status === ScormSessionStatusEnum::TERMINATED;
    }

    public function canResume(): bool
    {
        return in_array($this->status, [ScormSessionStatusEnum::ACTIVE, ScormSessionStatusEnum::SUSPENDED]);
    }

    public function suspend(array $suspendData = []): void
    {
        $this->status = ScormSessionStatusEnum::SUSPENDED;
        $this->suspendData = array_merge($this->suspendData, $suspendData);
        $this->lastAccessed = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function resume(): void
    {
        if ($this->isSuspended()) {
            $this->status = ScormSessionStatusEnum::ACTIVE;
            $this->lastAccessed = Carbon::now();
            $this->updatedAt = Carbon::now();
        }
    }

    public function complete(?float $finalScore = null): void
    {
        $this->status = ScormSessionStatusEnum::COMPLETED;
        if ($finalScore !== null) {
            $this->scoreRaw = $finalScore;
        }
        $this->completedAt = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function terminate(): void
    {
        $this->status = ScormSessionStatusEnum::TERMINATED;
        $this->updatedAt = Carbon::now();
    }

    public function updateLocation(string $location): void
    {
        $this->currentLocation = $location;
        $this->lastAccessed = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function addSessionTime(int $additionalSeconds): void
    {
        $this->totalTimeSeconds += $additionalSeconds;
        $this->lastAccessed = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    public function updateScore(?float $scoreRaw, ?float $scoreScaled = null): void
    {
        $this->scoreRaw = $scoreRaw;
        $this->scoreScaled = $scoreScaled;
        $this->lastAccessed = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    /**
     * Add activity to the log
     */
    public function addActivity(string $activityType, array $activityData, ?string $scormElement = null): void
    {
        $activity = [
            'type' => $activityType,
            'data' => $activityData,
            'scorm_element' => $scormElement,
            'timestamp' => Carbon::now()->toISOString(),
        ];

        $this->activityLog[] = $activity;
        $this->lastAccessed = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    /**
     * Update CMI data
     */
    public function updateCmiData(string $element, $value): void
    {
        if (!$this->cmiData) {
            $this->cmiData = [];
        }

        $this->cmiData[$element] = $value;
        $this->lastAccessed = Carbon::now();
        $this->updatedAt = Carbon::now();
    }

    /**
     * Get CMI data value
     */
    public function getCmiValue(string $element): mixed
    {
        return $this->cmiData[$element] ?? null;
    }

    /**
     * Get session duration in seconds
     */
    public function getDuration(): int
    {
        if (!$this->startedAt) {
            return 0;
        }

        $endTime = $this->completedAt ?? Carbon::now();
        return $this->startedAt->diffInSeconds($endTime);
    }

    /**
     * Check if session has timed out
     */
    public function isTimedOut(int $timeoutSeconds = 3600): bool
    {
        if (!$this->lastAccessed) {
            return false;
        }

        return $this->lastAccessed->diffInSeconds(Carbon::now()) > $timeoutSeconds;
    }

    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'package_id' => $this->packageId,
            'user_id' => $this->userId,
            'status' => $this->status->value,
            'suspend_data' => $this->suspendData,
            'activity_log' => $this->activityLog,
            'cmi_data' => $this->cmiData,
            'current_location' => $this->currentLocation,
            'score_raw' => $this->scoreRaw,
            'score_scaled' => $this->scoreScaled,
            'total_time_seconds' => $this->totalTimeSeconds,
            'started_at' => $this->startedAt?->toDateTimeString(),
            'last_accessed' => $this->lastAccessed?->toDateTimeString(),
            'completed_at' => $this->completedAt?->toDateTimeString(),
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
        ];
    }
}
