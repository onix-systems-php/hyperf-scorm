<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;
use OnixSystemsPHP\HyperfCore\Model\AbstractModel;
use OnixSystemsPHP\HyperfScorm\Constants\SessionStatuses;
use App\User\Model\User;

/**
 * @property int $id
 * @property int $package_id
 * @property int $user_id
 * @property string $status
 * @property string $session_token
 * @property string $lesson_status
 * @property string $lesson_mode
 * @property string $lesson_entry
 * @property string $lesson_credit
 * @property string $lesson_location
 * @property float $score_raw
 * @property float $score_scaled
 * @property string $completion_status
 * @property string $success_status
 * @property string $session_time
 * @property int $session_time_seconds
 * @property string $total_time
 * @property int $total_time_seconds
 * @property string $exit_mode
 * @property string $scorm_version
 * @property int $interactions_count
 * @property float $best_score
 * @property int $restart_count
 * @property array $suspend_data
 * @property string $student_name
 * @property string $launch_data
 * @property string $comments
 * @property string $comments_from_lms
 * @property bool $interactions_processed
 * @property Carbon $started_at
 * @property Carbon $last_accessed
 * @property Carbon $last_activity_at
 * @property Carbon $last_interaction_at
 * @property Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 *
 * @property ?Collection $interactions
 * @property ?ScormPackage $package
 * @property User $user
 */
class ScormUserSession extends AbstractModel
{
//    use SoftDeletes;

    protected ?string $table = 'scorm_user_sessions';

    protected array $fillable = [
        'package_id',
        'user_id',
        'status',
        'started_at',
        'last_accessed',
        'completed_at',
        'completed_at',
        'created_at',
        'updated_at',
        'session_token',
        'last_activity_at',
        'lesson_status',
        'lesson_mode',
        'lesson_entry',
        'lesson_credit',
        'lesson_location',
        'score_raw',
        'score_scaled',
        'completion_status',
        'success_status',
        'session_time',
        'total_time',
        'exit_mode',
        'scorm_version',
        'interactions_count',
        'best_score',
        'restart_count',
        'suspend_data',
        'student_name',
        'session_time_seconds',
        'total_time_seconds',
        'launch_data',
        'comments',
        'comments_from_lms',
        'interactions_processed',
        'last_interaction_at',
    ];

    protected array $casts = [
        'package_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'string',
        'suspend_data' => 'array',
        'current_location' => 'string',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(ScormInteraction::class, 'session_id');
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, SessionStatuses::FINAL_STATUSES);
    }

    public function isPassed(): bool
    {
        return $this->status === SessionStatuses::PASSED;
    }
}
