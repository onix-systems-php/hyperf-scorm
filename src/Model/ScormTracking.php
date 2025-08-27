<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Relations\BelongsTo;
use OnixSystemsPHP\HyperfCore\Model\AbstractModel;

/**
 * ScormTracking - SCORM CMI tracking data
 *
 * @property int $id
 * @property int $package_id
 * @property int $sco_id
 * @property int $user_id
 * @property int|null $attempt_id
 * @property string $element_name
 * @property string|null $element_value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScormTracking extends AbstractModel
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'scorm_tracking';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'package_id',
        'sco_id',
        'user_id',
        'attempt_id',
        'element_name',
        'element_value',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'package_id' => 'integer',
        'sco_id' => 'integer',
        'user_id' => 'integer',
        'attempt_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the SCORM package that owns the tracking record.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    /**
     * Get the SCO that owns the tracking record.
     */
    public function sco(): BelongsTo
    {
        return $this->belongsTo(ScormSco::class, 'sco_id');
    }

    /**
     * Get the attempt that owns the tracking record.
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ScormAttempt::class, 'attempt_id');
    }
}
