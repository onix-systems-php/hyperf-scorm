<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Model;

use Hyperf\Database\Model\Model;
use Carbon\Carbon;

/**
 * SCORM Activity Model
 */
class ScormActivity extends Model
{
    protected ?string $table = 'scorm_activities';

    protected array $fillable = [
        'session_id',
        'package_id',
        'user_id',
        'activity_type',
        'activity_data',
        'activity_timestamp',
    ];

    protected array $casts = [
        'activity_data' => 'array',
        'activity_timestamp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Activity types
    public const TYPE_QUESTION_ANSWER = 'question_answer';
    public const TYPE_LESSON_COMPLETE = 'lesson_complete';
    public const TYPE_LOCATION_CHANGE = 'location_change';

    /**
     * Get the session associated with this activity
     */
    public function session()
    {
        return $this->belongsTo(ScormUserSession::class, 'session_id');
    }

    /**
     * Get the package associated with this activity
     */
    public function package()
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    /**
     * Get the user associated with this activity
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
