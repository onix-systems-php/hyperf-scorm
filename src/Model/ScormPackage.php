<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use OnixSystemsPHP\HyperfCore\Model\AbstractModel;
use OnixSystemsPHP\HyperfScorm\Cast\ScormManifestDTOCast;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;

/**
 * ScormPackage
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $identifier
 * @property string $scorm_version
 * @property string $content_path
 * @property string|null $original_filename
 * @property int|null $file_size
 * @property string|null $file_hash
 * @property ScormManifestDTO $manifest_data
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
class ScormPackage extends AbstractModel
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected ?string $table = 'scorm_packages';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        'title',
        'description',
        'identifier',
        'scorm_version',
        'content_path',
        'original_filename',
        'file_size',
        'file_hash',
        'manifest_data',
        'is_active',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected array $casts = [
        'manifest_data' => ScormManifestDTOCast::class,
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the SCOs for the SCORM package.
     */
    public function scos(): HasMany
    {
        return $this->hasMany(ScormSco::class, 'package_id');
    }

    /**
     * Get the tracking records for the SCORM package.
     */
    public function trackingRecords(): HasMany
    {
        return $this->hasMany(ScormTracking::class, 'package_id');
    }

    /**
     * Get the attempts for the SCORM package.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(ScormAttempt::class, 'package_id');
    }

    /**
     * Check if package is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get launch URL from manifest data
     */
    public function getLaunchUrl(): ?string
    {
        if (!$this->manifest_data) {
            return null;
        }

        return $this->manifest_data->getPrimaryLaunchUrl();
    }

    /**
     * Get author from manifest data
     */
    public function getAuthor(): ?string
    {
        if (!$this->manifest_data) {
            return null;
        }

        $manifestDto = $this->manifest_data;

        if (method_exists($manifestDto, 'getAuthor')) {
            return $manifestDto->getAuthor();
        }

        return null;
    }

    /**
     * Get mastery score from manifest data
     */
    public function getMasteryScore(): ?float
    {
        if (!$this->manifest_data) {
            return null;
        }

        $manifestDto = $this->manifest_data;

        if (method_exists($manifestDto, 'getMasteryScore')) {
            return $manifestDto->getMasteryScore();
        }

        return null;
    }

    /**
     * Scope for active packages
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by SCORM version
     */
    public function scopeByVersion($query, string $version)
    {
        return $query->where('scorm_version', $version);
    }

    /**
     * Search by title and description
     */
    public function scopeSearch($query, string $term)
    {
        return $query->whereFullText(['title', 'description'], $term);
    }
}
