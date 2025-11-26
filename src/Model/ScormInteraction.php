<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Model;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $session_id
 * @property int $cmi_commit_id
 * @property string $interaction_id
 * @property string $type
 * @property string $description
 * @property array|null $learner_response
 * @property array|null $correct_response
 * @property string $result
 * @property float $weighting
 * @property int $latency_seconds
 * @property \DateTime|null $interaction_timestamp
 * @property array|null $objectives
 * @property \DateTime|null $created_at
 * @property \DateTime|null $updated_at
 *
 * @property ScormSession $session
 */
class ScormInteraction extends Model
{
    protected ?string $table = 'scorm_interactions';

    protected array $fillable = [
        'session_id',
        'cmi_commit_id',
        'interaction_id',
        'type',
        'description',
        'learner_response',
        'correct_response',
        'result',
        'weighting',
        'latency_seconds',
        'interaction_timestamp',
        'objectives',
        'created_at',
        'updated_at',
    ];

    protected array $casts = [
        'learner_response' => 'array', // todo create Cast
        'correct_response' => 'array', // todo create Cast
        'objectives' => 'array', // todo create Cast
        'interaction_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ScormSession::class, 'session_id');
    }
}
