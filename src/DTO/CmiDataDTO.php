<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * CMI (Computer Managed Instruction) Data Transfer Object
 *
 * Represents SCORM CMI data model for easier manipulation
 */
class CmiDataDTO extends AbstractDTO
{
    public function __construct(
        // Core CMI elements
        public ?string $lessonStatus = null,           // cmi.core.lesson_status
        public ?string $lessonLocation = null,         // cmi.core.lesson_location
        public ?float $scoreRaw = null,               // cmi.core.score.raw
        public ?float $scoreMin = null,               // cmi.core.score.min
        public ?float $scoreMax = null,               // cmi.core.score.max
        public ?string $totalTime = null,             // cmi.core.total_time
        public ?string $sessionTime = null,           // cmi.core.session_time
        public ?string $exit = null,                  // cmi.core.exit
        public ?string $credit = null,                // cmi.core.credit
        public ?string $entry = null,                 // cmi.core.entry

        // Student data
        public ?string $studentId = null,             // cmi.core.student_id
        public ?string $studentName = null,           // cmi.core.student_name

        // Lesson mode
        public ?string $mode = null,                  // cmi.core.lesson_mode

        // Suspend data
        public ?string $suspendData = null,           // cmi.suspend_data

        // Launch data
        public ?string $launchData = null,            // cmi.launch_data

        // Comments
        public ?string $commentsFromLearner = null,   // cmi.comments_from_learner
        public ?string $commentsFromLms = null,       // cmi.comments_from_lms

        // Objectives (array of objectives)
        public array $objectives = [],               // cmi.objectives

        // Interactions (array of interactions)
        public array $interactions = [],             // cmi.interactions

        // Custom/additional CMI data
        public array $additional = [],               // any other CMI elements
    ) {}

    /**
     * Get CMI element value by dot notation path
     */
    public function getCmiValue(string $element): mixed
    {
        return match ($element) {
            'cmi.core.lesson_status' => $this->lessonStatus,
            'cmi.core.lesson_location' => $this->lessonLocation,
            'cmi.core.score.raw' => $this->scoreRaw,
            'cmi.core.score.min' => $this->scoreMin,
            'cmi.core.score.max' => $this->scoreMax,
            'cmi.core.total_time' => $this->totalTime,
            'cmi.core.session_time' => $this->sessionTime,
            'cmi.core.exit' => $this->exit,
            'cmi.core.credit' => $this->credit,
            'cmi.core.entry' => $this->entry,
            'cmi.core.student_id' => $this->studentId,
            'cmi.core.student_name' => $this->studentName,
            'cmi.core.lesson_mode' => $this->mode,
            'cmi.suspend_data' => $this->suspendData,
            'cmi.launch_data' => $this->launchData,
            'cmi.comments_from_learner' => $this->commentsFromLearner,
            'cmi.comments_from_lms' => $this->commentsFromLms,
            default => $this->additional[$element] ?? null
        };
    }

    /**
     * Set CMI element value by dot notation path
     */
    public function setCmiValue(string $element, mixed $value): void
    {
        match ($element) {
            'cmi.core.lesson_status' => $this->lessonStatus = $value,
            'cmi.core.lesson_location' => $this->lessonLocation = $value,
            'cmi.core.score.raw' => $this->scoreRaw = $value,
            'cmi.core.score.min' => $this->scoreMin = $value,
            'cmi.core.score.max' => $this->scoreMax = $value,
            'cmi.core.total_time' => $this->totalTime = $value,
            'cmi.core.session_time' => $this->sessionTime = $value,
            'cmi.core.exit' => $this->exit = $value,
            'cmi.core.credit' => $this->credit = $value,
            'cmi.core.entry' => $this->entry = $value,
            'cmi.core.student_id' => $this->studentId = $value,
            'cmi.core.student_name' => $this->studentName = $value,
            'cmi.core.lesson_mode' => $this->mode = $value,
            'cmi.suspend_data' => $this->suspendData = $value,
            'cmi.launch_data' => $this->launchData = $value,
            'cmi.comments_from_learner' => $this->commentsFromLearner = $value,
            'cmi.comments_from_lms' => $this->commentsFromLms = $value,
            default => $this->additional[$element] = $value
        };
    }

    /**
     * Get all CMI data as flat array with dot notation keys
     */
    public function toCmiArray(): array
    {
        $data = [];

        if ($this->lessonStatus !== null) $data['cmi.core.lesson_status'] = $this->lessonStatus;
        if ($this->lessonLocation !== null) $data['cmi.core.lesson_location'] = $this->lessonLocation;
        if ($this->scoreRaw !== null) $data['cmi.core.score.raw'] = $this->scoreRaw;
        if ($this->scoreMin !== null) $data['cmi.core.score.min'] = $this->scoreMin;
        if ($this->scoreMax !== null) $data['cmi.core.score.max'] = $this->scoreMax;
        if ($this->totalTime !== null) $data['cmi.core.total_time'] = $this->totalTime;
        if ($this->sessionTime !== null) $data['cmi.core.session_time'] = $this->sessionTime;
        if ($this->exit !== null) $data['cmi.core.exit'] = $this->exit;
        if ($this->credit !== null) $data['cmi.core.credit'] = $this->credit;
        if ($this->entry !== null) $data['cmi.core.entry'] = $this->entry;
        if ($this->studentId !== null) $data['cmi.core.student_id'] = $this->studentId;
        if ($this->studentName !== null) $data['cmi.core.student_name'] = $this->studentName;
        if ($this->mode !== null) $data['cmi.core.lesson_mode'] = $this->mode;
        if ($this->suspendData !== null) $data['cmi.suspend_data'] = $this->suspendData;
        if ($this->launchData !== null) $data['cmi.launch_data'] = $this->launchData;
        if ($this->commentsFromLearner !== null) $data['cmi.comments_from_learner'] = $this->commentsFromLearner;
        if ($this->commentsFromLms !== null) $data['cmi.comments_from_lms'] = $this->commentsFromLms;

        // Add objectives with indices
        foreach ($this->objectives as $i => $objective) {
            if (isset($objective['id'])) $data["cmi.objectives.{$i}.id"] = $objective['id'];
            if (isset($objective['score'])) $data["cmi.objectives.{$i}.score.raw"] = $objective['score'];
            if (isset($objective['status'])) $data["cmi.objectives.{$i}.status"] = $objective['status'];
        }

        // Add interactions with indices
        foreach ($this->interactions as $i => $interaction) {
            if (isset($interaction['id'])) $data["cmi.interactions.{$i}.id"] = $interaction['id'];
            if (isset($interaction['type'])) $data["cmi.interactions.{$i}.type"] = $interaction['type'];
            if (isset($interaction['student_response'])) $data["cmi.interactions.{$i}.student_response"] = $interaction['student_response'];
            if (isset($interaction['result'])) $data["cmi.interactions.{$i}.result"] = $interaction['result'];
            if (isset($interaction['timestamp'])) $data["cmi.interactions.{$i}.timestamp"] = $interaction['timestamp'];
        }

        // Add additional elements
        foreach ($this->additional as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Create from flat CMI array with dot notation keys
     */
    public static function fromCmiArray(array $cmiData): self
    {
        $objectives = [];
        $interactions = [];
        $additional = [];

        // Parse objectives and interactions
        foreach ($cmiData as $key => $value) {
            if (str_starts_with($key, 'cmi.objectives.')) {
                $parts = explode('.', $key);
                if (isset($parts[2]) && is_numeric($parts[2])) {
                    $index = (int) $parts[2];
                    $field = implode('.', array_slice($parts, 3));
                    $objectives[$index][$field] = $value;
                }
            } elseif (str_starts_with($key, 'cmi.interactions.')) {
                $parts = explode('.', $key);
                if (isset($parts[2]) && is_numeric($parts[2])) {
                    $index = (int) $parts[2];
                    $field = implode('.', array_slice($parts, 3));
                    $interactions[$index][$field] = $value;
                }
            } elseif (!in_array($key, [
                'cmi.core.lesson_status', 'cmi.core.lesson_location', 'cmi.core.score.raw',
                'cmi.core.score.min', 'cmi.core.score.max', 'cmi.core.total_time',
                'cmi.core.session_time', 'cmi.core.exit', 'cmi.core.credit',
                'cmi.core.entry', 'cmi.core.student_id', 'cmi.core.student_name',
                'cmi.core.lesson_mode', 'cmi.suspend_data', 'cmi.launch_data',
                'cmi.comments_from_learner', 'cmi.comments_from_lms'
            ])) {
                $additional[$key] = $value;
            }
        }

        return new self(
            lessonStatus: $cmiData['cmi.core.lesson_status'] ?? null,
            lessonLocation: $cmiData['cmi.core.lesson_location'] ?? null,
            scoreRaw: isset($cmiData['cmi.core.score.raw']) ? (float) $cmiData['cmi.core.score.raw'] : null,
            scoreMin: isset($cmiData['cmi.core.score.min']) ? (float) $cmiData['cmi.core.score.min'] : null,
            scoreMax: isset($cmiData['cmi.core.score.max']) ? (float) $cmiData['cmi.core.score.max'] : null,
            totalTime: $cmiData['cmi.core.total_time'] ?? null,
            sessionTime: $cmiData['cmi.core.session_time'] ?? null,
            exit: $cmiData['cmi.core.exit'] ?? null,
            credit: $cmiData['cmi.core.credit'] ?? null,
            entry: $cmiData['cmi.core.entry'] ?? null,
            studentId: $cmiData['cmi.core.student_id'] ?? null,
            studentName: $cmiData['cmi.core.student_name'] ?? null,
            mode: $cmiData['cmi.core.lesson_mode'] ?? null,
            suspendData: $cmiData['cmi.suspend_data'] ?? null,
            launchData: $cmiData['cmi.launch_data'] ?? null,
            commentsFromLearner: $cmiData['cmi.comments_from_learner'] ?? null,
            commentsFromLms: $cmiData['cmi.comments_from_lms'] ?? null,
            objectives: array_values($objectives),
            interactions: array_values($interactions),
            additional: $additional,
        );
    }
}
