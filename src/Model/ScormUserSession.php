<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Collection;
use Hyperf\Database\Model\Relations\BelongsTo;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use OnixSystemsPHP\HyperfCore\Model\AbstractModel;
use OnixSystemsPHP\HyperfScorm\Constants\SessionStatuses;
use OnixSystemsPHP\HyperfSocialite\One\User;

/**
 * @property int $id
 * @property int $package_id
 * @property int $user_id
 * @property string $status
 * @property string $suspend_data
 * @property string $current_location
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ?Collection $interactions
 * @property ?ScormPackage $package
 * @property ?User $user
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
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trackingRecords(): HasMany
    {
        return $this->hasMany(ScormTracking::class, 'attempt_id');
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
