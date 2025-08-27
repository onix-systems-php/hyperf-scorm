<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use Carbon\Carbon;

/**
 * DTO for SCORM resume functionality data
 * Contains all information needed to resume a SCORM session from exact stopping point
 */
class ResumeDataDTO extends AbstractDTO
{
    public function __construct(
        public readonly string $currentLocation,
        public readonly string $lessonStatus,
        public readonly ?float $scoreRaw,
        public readonly ?float $scoreScaled,
        public readonly int $sessionTime, // in seconds
        public readonly array $suspendData,
        public readonly array $interactions,
        public readonly array $objectives,
        public readonly Carbon $lastAccessed
    ) {}

    /**
     * Convert resume data to SCORM-specific data model values
     */
    public function toScormValues(ScormVersionEnum $version): array
    {
        if ($version === ScormVersionEnum::SCORM_12) {
            return [
                'cmi.core.lesson_location' => $this->currentLocation,
                'cmi.core.lesson_status' => $this->lessonStatus,
                'cmi.core.score.raw' => $this->scoreRaw,
                'cmi.core.session_time' => $this->formatSessionTime($this->sessionTime),
                'cmi.core.total_time' => $this->formatSessionTime($this->sessionTime),
                'cmi.core.entry' => $this->determineEntryValue(),
                'cmi.suspend_data' => json_encode($this->suspendData),
                'cmi.interactions._count' => count($this->interactions),
                'cmi.objectives._count' => count($this->objectives),
            ];
        } else {
            return [
                'cmi.location' => $this->currentLocation,
                'cmi.completion_status' => $this->mapLessonStatusToCompletion($this->lessonStatus),
                'cmi.success_status' => $this->mapLessonStatusToSuccess($this->lessonStatus),
                'cmi.score.scaled' => $this->scoreScaled,
                'cmi.score.raw' => $this->scoreRaw,
                'cmi.session_time' => $this->formatSessionTimeISO($this->sessionTime),
                'cmi.total_time' => $this->formatSessionTimeISO($this->sessionTime),
                'cmi.entry' => $this->determineEntryValue(),
                'cmi.suspend_data' => json_encode($this->suspendData),
                'cmi.interactions._count' => count($this->interactions),
                'cmi.objectives._count' => count($this->objectives),
            ];
        }
    }

    /**
     * Get interaction data for SCORM API
     */
    public function getInteractionData(ScormVersionEnum $version): array
    {
        $interactionData = [];

        foreach ($this->interactions as $index => $interaction) {
            if ($version === ScormVersionEnum::SCORM_12) {
                $interactionData["cmi.interactions.{$index}.id"] = $interaction['id'] ?? '';
                $interactionData["cmi.interactions.{$index}.type"] = $interaction['type'] ?? '';
                $interactionData["cmi.interactions.{$index}.student_response"] = $interaction['student_response'] ?? '';
                $interactionData["cmi.interactions.{$index}.result"] = $interaction['result'] ?? '';
                $interactionData["cmi.interactions.{$index}.weighting"] = $interaction['weighting'] ?? '';
                $interactionData["cmi.interactions.{$index}.latency"] = $interaction['latency'] ?? '';
            } else {
                $interactionData["cmi.interactions.{$index}.id"] = $interaction['id'] ?? '';
                $interactionData["cmi.interactions.{$index}.type"] = $interaction['type'] ?? '';
                $interactionData["cmi.interactions.{$index}.learner_response"] = $interaction['learner_response'] ?? '';
                $interactionData["cmi.interactions.{$index}.result"] = $interaction['result'] ?? '';
                $interactionData["cmi.interactions.{$index}.weighting"] = $interaction['weighting'] ?? '';
                $interactionData["cmi.interactions.{$index}.latency"] = $interaction['latency'] ?? '';
                $interactionData["cmi.interactions.{$index}.description"] = $interaction['description'] ?? '';
            }
        }

        return $interactionData;
    }

    /**
     * Get objective data for SCORM API
     */
    public function getObjectiveData(ScormVersionEnum $version): array
    {
        $objectiveData = [];

        foreach ($this->objectives as $index => $objective) {
            if ($version === ScormVersionEnum::SCORM_12) {
                $objectiveData["cmi.objectives.{$index}.id"] = $objective['id'] ?? '';
                $objectiveData["cmi.objectives.{$index}.status"] = $objective['status'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.raw"] = $objective['score_raw'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.min"] = $objective['score_min'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.max"] = $objective['score_max'] ?? '';
            } else {
                $objectiveData["cmi.objectives.{$index}.id"] = $objective['id'] ?? '';
                $objectiveData["cmi.objectives.{$index}.success_status"] = $objective['success_status'] ?? '';
                $objectiveData["cmi.objectives.{$index}.completion_status"] = $objective['completion_status'] ?? '';
                $objectiveData["cmi.objectives.{$index}.progress_measure"] = $objective['progress_measure'] ?? '';
                $objectiveData["cmi.objectives.{$index}.description"] = $objective['description'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.scaled"] = $objective['score_scaled'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.raw"] = $objective['score_raw'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.min"] = $objective['score_min'] ?? '';
                $objectiveData["cmi.objectives.{$index}.score.max"] = $objective['score_max'] ?? '';
            }
        }

        return $objectiveData;
    }

    /**
     * Get all resume data combined for JavaScript API
     */
    public function getAllResumeData(ScormVersionEnum $version): array
    {
        $data = $this->toScormValues($version);
        $data = array_merge($data, $this->getInteractionData($version));
        $data = array_merge($data, $this->getObjectiveData($version));

        return $data;
    }

    /**
     * Format session time for SCORM 1.2 (HHHH:MM:SS.SS)
     */
    private function formatSessionTime(int $seconds): string
    {
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%04d:%02d:%02d.00', $hours, $minutes, $secs);
    }

    /**
     * Format session time for SCORM 2004 (ISO 8601 duration)
     */
    private function formatSessionTimeISO(int $seconds): string
    {
        $hours = intval($seconds / 3600);
        $minutes = intval(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('PT%dH%dM%dS', $hours, $minutes, $secs);
    }

    /**
     * Map lesson status to completion status (SCORM 2004)
     */
    private function mapLessonStatusToCompletion(string $lessonStatus): string
    {
        return match($lessonStatus) {
            'completed', 'passed' => 'completed',
            'incomplete', 'failed' => 'incomplete',
            'not attempted' => 'not attempted',
            'browsed' => 'incomplete',
            default => 'unknown'
        };
    }

    /**
     * Map lesson status to success status (SCORM 2004)
     */
    private function mapLessonStatusToSuccess(string $lessonStatus): string
    {
        return match($lessonStatus) {
            'passed' => 'passed',
            'failed' => 'failed',
            default => 'unknown'
        };
    }

    /**
     * Determine entry value (resume vs ab-initio)
     */
    private function determineEntryValue(): string
    {
        return !empty($this->currentLocation) || !empty($this->suspendData) ? 'resume' : 'ab-initio';
    }

    /**
     * Check if this represents a fresh start
     */
    public function isFreshStart(): bool
    {
        return empty($this->currentLocation) &&
               empty($this->suspendData) &&
               $this->sessionTime === 0 &&
               empty($this->interactions);
    }

    /**
     * Check if this represents a completed session
     */
    public function isCompleted(): bool
    {
        return in_array($this->lessonStatus, ['completed', 'passed']);
    }

    /**
     * Check if this session was suspended
     */
    public function wasSuspended(): bool
    {
        return !empty($this->suspendData) && !$this->isCompleted();
    }

    /**
     * Get progress percentage estimate
     */
    public function getProgressPercentage(): int
    {
        if ($this->isCompleted()) {
            return 100;
        }

        // Estimate based on available data
        $score = 0;

        if (!empty($this->currentLocation)) {
            $score += 30; // Has made progress through content
        }

        if (!empty($this->interactions)) {
            $score += 40; // Has answered questions
        }

        if ($this->sessionTime > 300) { // More than 5 minutes
            $score += 20; // Significant time spent
        }

        if (!empty($this->objectives)) {
            $score += 10; // Has objective progress
        }

        return min($score, 99); // Max 99% unless actually completed
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'current_location' => $this->currentLocation,
            'lesson_status' => $this->lessonStatus,
            'score_raw' => $this->scoreRaw,
            'score_scaled' => $this->scoreScaled,
            'session_time' => $this->sessionTime,
            'session_time_formatted' => $this->formatSessionTime($this->sessionTime),
            'suspend_data' => $this->suspendData,
            'interactions' => $this->interactions,
            'objectives' => $this->objectives,
            'last_accessed' => $this->lastAccessed->toISOString(),
            'is_fresh_start' => $this->isFreshStart(),
            'is_completed' => $this->isCompleted(),
            'was_suspended' => $this->wasSuspended(),
            'progress_percentage' => $this->getProgressPercentage(),
            'entry_value' => $this->determineEntryValue()
        ];
    }
}
