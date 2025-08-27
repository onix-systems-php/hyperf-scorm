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
use Hyperf\Database\Model\Relations\HasMany;
use OnixSystemsPHP\HyperfCore\Model\AbstractModel;

/**
 * ScormSco - Sharable Content Object
 *
 * @property int $id
 * @property int $package_id
 * @property string $identifier
 * @property string $title
 * @property string|null $launch_url
 * @property array|null $prerequisites
 * @property array|null $parameters
 * @property string|null $masteryScore
 * @property string|null $maxTimeAllowed
 * @property string|null $timeLimitAction
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScormSco extends AbstractModel
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = 'scorm_scos';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'package_id',
        'identifier',
        'title',
        'launch_url',
        'prerequisites',
        'parameters',
        'mastery_score',
        'max_time_allowed',
        'time_limit_action',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'package_id' => 'integer',
        'prerequisites' => 'array',
        'parameters' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the SCORM package that owns the SCO.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    /**
     * Get the tracking records for the SCO.
     */
    public function trackingRecords(): HasMany
    {
        return $this->hasMany(ScormTracking::class, 'sco_id');
    }
}
