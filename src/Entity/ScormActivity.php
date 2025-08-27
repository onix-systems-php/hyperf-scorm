<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Entity;

use Carbon\Carbon;

/**
 * SCORM Activity Entity - represents a user's learning activity/interaction
 */
class ScormActivity
{
    public const TYPE_QUESTION_ANSWER = 'question_answer';
    public const TYPE_LESSON_COMPLETE = 'lesson_complete';
    public const TYPE_INTERACTION = 'interaction';
    public const TYPE_SCORE_UPDATE = 'score_update';
    public const TYPE_LOCATION_CHANGE = 'location_change';
    public const TYPE_SESSION_START = 'session_start';
    public const TYPE_SESSION_SUSPEND = 'session_suspend';
    public const TYPE_SESSION_TERMINATE = 'session_terminate';

    private int $id;
    private string $sessionId;
    private int $packageId;
    private int $userId;
    private string $activityType;
    private array $activityData;
    private ?string $scormElement;
    private ?string $scormValue;
    private Carbon $activityTimestamp;
    private Carbon $createdAt;
    private Carbon $updatedAt;

    public function __construct(
        int $id,
        string $sessionId,
        int $packageId,
        int $userId,
        string $activityType,
        array $activityData,
        ?string $scormElement = null,
        ?string $scormValue = null,
        ?Carbon $activityTimestamp = null,
        ?Carbon $createdAt = null,
        ?Carbon $updatedAt = null
    ) {
        $this->id = $id;
        $this->sessionId = $sessionId;
        $this->packageId = $packageId;
        $this->userId = $userId;
        $this->activityType = $activityType;
        $this->activityData = $activityData;
        $this->scormElement = $scormElement;
        $this->scormValue = $scormValue;
        $this->activityTimestamp = $activityTimestamp ?? Carbon::now();
        $this->createdAt = $createdAt ?? Carbon::now();
        $this->updatedAt = $updatedAt ?? Carbon::now();
    }

    /**
     * Create activity from array data
     */
    public static function create(array $data): self
    {
        return new self(
            id: $data['id'] ?? 0,
            sessionId: $data['session_id'],
            packageId: $data['package_id'],
            userId: $data['user_id'],
            activityType: $data['activity_type'],
            activityData: $data['activity_data'] ?? [],
            scormElement: $data['scorm_element'] ?? null,
            scormValue: $data['scorm_value'] ?? null,
            activityTimestamp: isset($data['activity_timestamp']) ? Carbon::parse($data['activity_timestamp']) : null,
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null
        );
    }

    /**
     * Create question answer activity
     */
    public static function createQuestionAnswer(
        string $sessionId,
        int $packageId,
        int $userId,
        string $questionId,
        string $answer,
        bool $isCorrect,
        ?float $score = null
    ): self {
        $activityData = [
            'question_id' => $questionId,
            'answer' => $answer,
            'is_correct' => $isCorrect,
            'score' => $score,
            'timestamp' => Carbon::now()->toISOString()
        ];

        return new self(
            id: 0,
            sessionId: $sessionId,
            packageId: $packageId,
            userId: $userId,
            activityType: self::TYPE_QUESTION_ANSWER,
            activityData: $activityData,
            scormElement: 'cmi.interactions.' . $questionId . '.result',
            scormValue: $isCorrect ? 'correct' : 'incorrect'
        );
    }

    /**
     * Create lesson completion activity
     */
    public static function createLessonCompletion(
        string $sessionId,
        int $packageId,
        int $userId,
        string $lessonId,
        float $completionPercentage,
        ?float $finalScore = null
    ): self {
        $activityData = [
            'lesson_id' => $lessonId,
            'completion_percentage' => $completionPercentage,
            'final_score' => $finalScore,
            'timestamp' => Carbon::now()->toISOString()
        ];

        return new self(
            id: 0,
            sessionId: $sessionId,
            packageId: $packageId,
            userId: $userId,
            activityType: self::TYPE_LESSON_COMPLETE,
            activityData: $activityData,
            scormElement: 'cmi.completion_status',
            scormValue: $completionPercentage >= 100 ? 'completed' : 'incomplete'
        );
    }

    /**
     * Create location change activity
     */
    public static function createLocationChange(
        string $sessionId,
        int $packageId,
        int $userId,
        string $newLocation,
        ?string $previousLocation = null
    ): self {
        $activityData = [
            'new_location' => $newLocation,
            'previous_location' => $previousLocation,
            'timestamp' => Carbon::now()->toISOString()
        ];

        return new self(
            id: 0,
            sessionId: $sessionId,
            packageId: $packageId,
            userId: $userId,
            activityType: self::TYPE_LOCATION_CHANGE,
            activityData: $activityData,
            scormElement: 'cmi.location',
            scormValue: $newLocation
        );
    }

    // Getters
    public function getId(): int
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getPackageId(): int
    {
        return $this->packageId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getActivityType(): string
    {
        return $this->activityType;
    }

    public function getActivityData(): array
    {
        return $this->activityData;
    }

    public function getScormElement(): ?string
    {
        return $this->scormElement;
    }

    public function getScormValue(): ?string
    {
        return $this->scormValue;
    }

    public function getActivityTimestamp(): Carbon
    {
        return $this->activityTimestamp;
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
    public function isQuestionAnswer(): bool
    {
        return $this->activityType === self::TYPE_QUESTION_ANSWER;
    }

    public function isLessonComplete(): bool
    {
        return $this->activityType === self::TYPE_LESSON_COMPLETE;
    }

    public function isInteraction(): bool
    {
        return $this->activityType === self::TYPE_INTERACTION;
    }

    public function isScoreUpdate(): bool
    {
        return $this->activityType === self::TYPE_SCORE_UPDATE;
    }

    public function isLocationChange(): bool
    {
        return $this->activityType === self::TYPE_LOCATION_CHANGE;
    }

    public function isSessionEvent(): bool
    {
        return in_array($this->activityType, [
            self::TYPE_SESSION_START,
            self::TYPE_SESSION_SUSPEND,
            self::TYPE_SESSION_TERMINATE
        ]);
    }

    /**
     * Get question answer result
     */
    public function getQuestionResult(): ?bool
    {
        if (!$this->isQuestionAnswer()) {
            return null;
        }

        return $this->activityData['is_correct'] ?? null;
    }

    /**
     * Get score from activity
     */
    public function getActivityScore(): ?float
    {
        return match ($this->activityType) {
            self::TYPE_QUESTION_ANSWER => $this->activityData['score'] ?? null,
            self::TYPE_LESSON_COMPLETE => $this->activityData['final_score'] ?? null,
            self::TYPE_SCORE_UPDATE => $this->activityData['score'] ?? null,
            default => null
        };
    }

    /**
     * Get completion percentage for lesson activities
     */
    public function getCompletionPercentage(): ?float
    {
        if (!$this->isLessonComplete()) {
            return null;
        }

        return $this->activityData['completion_percentage'] ?? null;
    }

    /**
     * Convert entity to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->sessionId,
            'package_id' => $this->packageId,
            'user_id' => $this->userId,
            'activity_type' => $this->activityType,
            'activity_data' => $this->activityData,
            'scorm_element' => $this->scormElement,
            'scorm_value' => $this->scormValue,
            'activity_timestamp' => $this->activityTimestamp->toDateTimeString(),
            'created_at' => $this->createdAt->toDateTimeString(),
            'updated_at' => $this->updatedAt->toDateTimeString(),
        ];
    }

    /**
     * Get all valid activity types
     */
    public static function getValidTypes(): array
    {
        return [
            self::TYPE_QUESTION_ANSWER,
            self::TYPE_LESSON_COMPLETE,
            self::TYPE_INTERACTION,
            self::TYPE_SCORE_UPDATE,
            self::TYPE_LOCATION_CHANGE,
            self::TYPE_SESSION_START,
            self::TYPE_SESSION_SUSPEND,
            self::TYPE_SESSION_TERMINATE,
        ];
    }
}
