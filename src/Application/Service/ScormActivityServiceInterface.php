<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Application\Service;

use OnixSystemsPHP\HyperfScorm\Model\ScormActivity;

/**
 * Application Service Interface for SCORM activity management
 */
interface ScormActivityServiceInterface
{
    /**
     * Record question answer activity
     */
    public function recordQuestionAnswer(
        string $sessionId,
        string $questionId,
        string $answer,
        bool $isCorrect,
        ?float $score = null
    ): ScormActivity;

    /**
     * Record lesson completion
     */
    public function recordLessonCompletion(
        string $sessionId,
        string $lessonId,
        float $completionPercentage,
        ?float $finalScore = null
    ): ScormActivity;

    /**
     * Record user interaction
     */
    public function recordInteraction(
        string $sessionId,
        string $interactionType,
        array $interactionData
    ): ScormActivity;

    /**
     * Record location change (navigation)
     */
    public function recordLocationChange(
        string $sessionId,
        string $newLocation,
        ?string $previousLocation = null
    ): ScormActivity;

    /**
     * Record session lifecycle events
     */
    public function recordSessionStart(string $sessionId): ScormActivity;
    public function recordSessionSuspend(string $sessionId, array $suspendData): ScormActivity;
    public function recordSessionTerminate(string $sessionId): ScormActivity;

    /**
     * Get user's learning progress for package
     */
    public function getUserProgress(int $userId, int $packageId): array;

    /**
     * Get session activity summary
     */
    public function getSessionSummary(string $sessionId): array;

    /**
     * Get question answers for session
     */
    public function getQuestionAnswers(string $sessionId): array;

    /**
     * Calculate session score
     */
    public function calculateSessionScore(string $sessionId): float;

    /**
     * Get package analytics
     */
    public function getPackageAnalytics(int $packageId): array;
}
