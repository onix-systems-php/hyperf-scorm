<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Model;

use Carbon\Carbon;
use Hyperf\Database\Model\Relations\HasMany;
use Hyperf\Database\Model\SoftDeletes;
use OnixSystemsPHP\HyperfCore\Model\AbstractModel;
use OnixSystemsPHP\HyperfScorm\Cast\ScormManifestDTOCast;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;

/**
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

    protected ?string $table = 'scorm_packages';

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

    protected array $casts = [
        'manifest_data' => ScormManifestDTOCast::class,
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function scos(): HasMany
    {
        return $this->hasMany(ScormSco::class, 'package_id');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function getLaunchUrl(): ?string
    {
        if (!$this->manifest_data) {
            return null;
        }

        return $this->manifest_data->getPrimaryLaunchUrl();
    }

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
}
