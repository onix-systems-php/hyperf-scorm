<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Model;

use Hyperf\Database\Model\Model;

/**
 * @property int $id
 * @property int $session_id
 * @property int $package_id
 * @property int $user_id
 * @property string $activity_type
 * @property array $activity_data
 * @property \Carbon\Carbon $activity_timestamp
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property ScormSession $session
 * @property ScormPackage $package
 * @property User $user
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

    public function session()
    {
        return $this->belongsTo(ScormSession::class, 'session_id');
    }

    public function package()
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');//TODO move user move to config
    }
}
