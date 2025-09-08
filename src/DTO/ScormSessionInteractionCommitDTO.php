<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormSessionInteractionCommitDTO extends AbstractDTO
{
    public string $id;
    public string $type = 'choice';
    public string $description = '';
    public ?array $learner_response = [];
    public ?array $correct_response = [];
    public string $result = 'neutral';
    public ?float $weighting = null;
    public ?float $latency_seconds = null;
    public string $interaction_timestamp;
    public ?array $objectives = [];
    public string $created_at;


//cmi_commit_id	bigint NULL // notice ???
}
