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
 * @property int $id
 * @property int $package_id
 * @property string $identifier
 * @property string $title
 * @property string $launcher_path
 * @property null|array $prerequisites
 * @property null|array $parameters
 * @property null|string $masteryScore
 * @property null|string $maxTimeAllowed
 * @property null|string $timeLimitAction
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property ScormPackage $package
 */
class ScormSco extends AbstractModel
{
    protected ?string $table = 'scorm_scos';

    protected array $fillable = [
        'package_id',
        'identifier',
        'title',
        'launcher_path',
        'prerequisites',
        'parameters',
        'mastery_score',
        'max_time_allowed',
        'time_limit_action',
    ];

    protected array $casts = [
        'package_id' => 'integer',
        'prerequisites' => 'array',
        'parameters' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }
}
