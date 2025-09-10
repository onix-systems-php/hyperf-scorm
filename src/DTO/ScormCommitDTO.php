<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use ClassTransformer\Attributes\ConvertArray;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCommitInteractionDTO;

class ScormCommitDTO extends AbstractDTO
{
    public string $student_id;

    public string $student_name;

    public ScormCommitSessionDTO $session;

    public ScormCommitLessonDTO $lesson;

    public ?int $score = 0;

    public ?int $score_percentage = null;

    #[ConvertArray(ScormCommitInteractionDTO::class)]
    public array $interactions = [];

    public ?string $completed_at = null;

    public function getInteractionsCount(): int
    {
        return count($this->interactions);
    }

    public function getCompletedTimestamp(): ?string
    {
        if (!$this->completed_at) {
            return null;
        }

        $date = \DateTime::createFromFormat(\DateTime::ATOM, $this->completed_at);
        if (!$date) {
            $date = new \DateTime($this->completed_at);
        }

        return $date->format('Y-m-d\TH:i:s.v\Z');
    }

    public function isCompleted(): bool
    {
        return in_array($this->lesson->status, ['completed', 'passed', 'failed']);
    }

    public function isPassed(): bool
    {
        return $this->lesson->status === 'passed';
    }
}
