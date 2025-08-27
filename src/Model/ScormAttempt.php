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
use OnixSystemsPHP\HyperfScorm\Cast\CmiDataCast;
use OnixSystemsPHP\HyperfScorm\DTO\CmiDataDTO;

/**
 * ScormAttempt - SCORM learning attempt/session
 *
 * @property int $id
 * @property int $package_id
 * @property int $user_id
 * @property string $status
 * @property float|null $score
 * @property int|null $time_spent
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ScormAttempt extends AbstractModel
{
    const STATUS_NOT_ATTEMPTED = 'not_attempted';
    const STATUS_INCOMPLETE = 'incomplete';
    const STATUS_COMPLETED = 'completed';
    const STATUS_PASSED = 'passed';
    const STATUS_FAILED = 'failed';
    const STATUS_BROWSED = 'browsed';

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'scorm_attempts';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'package_id',
        'user_id',
        'status',
        'score',
        'time_spent',
        'started_at',
        'completed_at',
        'cmi_data',
        'lesson_location',
        'lesson_status',
        'suspend_data',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'package_id' => 'integer',
        'user_id' => 'integer',
        'score' => 'float',
        'time_spent' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cmi_data' => CmiDataCast::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the SCORM package that owns the attempt.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(ScormPackage::class, 'package_id');
    }

    /**
     * Get the tracking records for the attempt.
     */
    public function trackingRecords(): HasMany
    {
        return $this->hasMany(ScormTracking::class, 'attempt_id');
    }

    /**
     * Check if the attempt is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_PASSED,
            self::STATUS_FAILED,
        ]);
    }

    /**
     * Check if the attempt is passed.
     */
    public function isPassed(): bool
    {
        return $this->status === self::STATUS_PASSED;
    }

    /**
     * Get CMI element value using DTO
     */
    public function getCmiValue(string $element): mixed
    {
        $cmiDto = $this->getCmiDto();
        return $cmiDto?->getCmiValue($element);
    }

    /**
     * Set CMI element value using DTO
     */
    public function setCmiValue(string $element, mixed $value): void
    {
        $cmiDto = $this->getCmiDto() ?? new CmiDataDTO();
        $cmiDto->setCmiValue($element, $value);
        $this->cmi_data = $cmiDto;
    }

    /**
     * Set multiple CMI values using DTO
     */
    public function setCmiValues(array $values): void
    {
        $cmiDto = $this->getCmiDto() ?? new CmiDataDTO();
        foreach ($values as $element => $value) {
            $cmiDto->setCmiValue($element, $value);
        }
        $this->cmi_data = $cmiDto;
    }

    /**
     * Get CMI DTO
     */
    public function getCmiDto(): ?CmiDataDTO
    {
        return $this->cmi_data;
    }

    /**
     * Set CMI DTO
     */
    public function setCmiDto(CmiDataDTO $cmiDto): void
    {
        $this->cmi_data = $cmiDto;
    }

    /**
     * Get all CMI data as array
     */
    public function getCmiDataArray(): array
    {
        return $this->getCmiDto()?->toCmiArray() ?? [];
    }

    /**
     * Initialize empty CMI data
     */
    public function initializeCmiData(): void
    {
        if (!$this->cmi_data) {
            $this->cmi_data = new CmiDataDTO(
                lessonStatus: 'not attempted',
                entry: 'ab-initio',
                mode: 'normal',
                credit: 'credit'
            );
        }
    }
}
