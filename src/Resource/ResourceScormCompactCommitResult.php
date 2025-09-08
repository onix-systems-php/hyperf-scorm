<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ResourceScormCompactCommitResult',
    properties: [
        new OA\Property(property: 'session_id', type: 'integer', example: 123),
        new OA\Property(property: 'student_id', type: 'string', example: 'Guest'),
        new OA\Property(property: 'lesson_status', type: 'string', example: 'incomplete'),
        new OA\Property(property: 'score', type: 'integer', nullable: true, example: 75),
        new OA\Property(property: 'score_percentage', type: 'integer', nullable: true, example: 75),
        new OA\Property(property: 'interactions_count', type: 'integer', example: 3),
        new OA\Property(property: 'session_time_seconds', type: 'integer', nullable: true, example: 300),
        new OA\Property(property: 'is_completed', type: 'boolean', example: false),
        new OA\Property(property: 'is_passed', type: 'boolean', example: false),
        new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', example: '2025-08-29T18:26:40.006Z'),
        new OA\Property(property: 'message', type: 'string', example: 'Compact commit processed successfully'),
        new OA\Property(
            property: 'summary',
            type: 'object',
            properties: [
                new OA\Property(property: 'total_interactions', type: 'integer', example: 3),
                new OA\Property(property: 'correct_interactions', type: 'integer', example: 1),
                new OA\Property(property: 'incorrect_interactions', type: 'integer', example: 2),
                new OA\Property(property: 'session_duration_formatted', type: 'string', example: '5m 0s'),
                new OA\Property(property: 'completion_percentage', type: 'integer', example: 75),
            ]
        ),
    ]
)]
class ResourceScormCompactCommitResult extends AbstractResource
{
    /**
     * @method __construct(array $resource)
     * @property array $resource
     */

    public function toArray(): array
    {
        return [
            'session_id' => $this->resource['session_id'],
            'student_id' => $this->resource['student_id'],
            'lesson_status' => $this->resource['lesson_status'],
            'score' => $this->resource['score'],
            'score_percentage' => $this->resource['score_percentage'],
            'interactions_count' => $this->resource['interactions_count'],
            'session_time_seconds' => $this->resource['session_time_seconds'],
            'is_completed' => $this->resource['is_completed'],
            'is_passed' => $this->resource['is_passed'],
            'processed_at' => $this->resource['processed_at'],
            'message' => $this->getMessage(),
            'summary' => $this->getSummary(),
        ];
    }

    /**
     * Get success message based on lesson status
     */
    private function getMessage(): string
    {
        $status = $this->resource['lesson_status'];
        
        return match($status) {
            'completed' => 'Lesson completed successfully',
            'passed' => 'Lesson completed and passed',
            'failed' => 'Lesson completed but failed',
            'incomplete' => 'Progress saved successfully',
            'browsed' => 'Lesson browsed, progress saved',
            default => 'Compact commit processed successfully'
        };
    }

    /**
     * Get summary statistics
     */
    private function getSummary(): array
    {
        $sessionTime = $this->resource['session_time_seconds'] ?? 0;
        $interactionsCount = $this->resource['interactions_count'] ?? 0;
        $scorePercentage = $this->resource['score_percentage'] ?? 0;

        return [
            'total_interactions' => $interactionsCount,
            'correct_interactions' => $this->estimateCorrectInteractions(),
            'incorrect_interactions' => $interactionsCount - $this->estimateCorrectInteractions(),
            'session_duration_formatted' => $this->formatSessionTime($sessionTime),
            'completion_percentage' => $this->getCompletionPercentage(),
        ];
    }

    /**
     * Estimate correct interactions based on score percentage
     */
    private function estimateCorrectInteractions(): int
    {
        $interactionsCount = $this->resource['interactions_count'] ?? 0;
        $scorePercentage = $this->resource['score_percentage'] ?? 0;

        if ($interactionsCount === 0) {
            return 0;
        }

        return (int)round(($scorePercentage / 100) * $interactionsCount);
    }

    /**
     * Format session time in human-readable format
     */
    private function formatSessionTime(int $seconds): string
    {
        if ($seconds === 0) {
            return '0s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        
        if ($minutes > 0) {
            $parts[] = "{$minutes}m";
        }
        
        if ($secs > 0 || empty($parts)) {
            $parts[] = "{$secs}s";
        }

        return implode(' ', $parts);
    }

    /**
     * Get completion percentage based on status and score
     */
    private function getCompletionPercentage(): int
    {
        $status = $this->resource['lesson_status'];
        $scorePercentage = $this->resource['score_percentage'] ?? 0;

        return match($status) {
            'completed', 'passed', 'failed' => 100,
            'incomplete', 'browsed' => max(25, $scorePercentage), // At least 25% for attempted
            default => 0
        };
    }
}