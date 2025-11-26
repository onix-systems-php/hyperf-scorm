<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Repository;

use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationRequestDTO;
use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationResultDTO;
use OnixSystemsPHP\HyperfCore\Model\Builder;
use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\Filter\ScormPackageFilter;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;

/**
 * @method ScormPackage create(array $data)
 * @method ScormPackage update(ScormPackage $model, array $data)
 * @method ScormPackage save(ScormPackage $model)
 * @method bool delete(ScormPackage $model)
 * @method Builder|ScormPackageRepository finder(string $type, ...$parameters)
 * @method null|ScormPackage fetchOne(bool $lock, bool $force)
 */
class ScormPackageRepository extends AbstractRepository
{
    protected string $modelClass = ScormPackage::class;

    public function getPaginated(
        array $filters,
        PaginationRequestDTO $paginationDTO,
        array $contain = []
    ): PaginationResultDTO {
        $query = $this->query()->filter(new ScormPackageFilter($filters));
        if (! empty($contain)) {
            $query->with($contain);
        }
        return $query->paginateDTO($paginationDTO);
    }

    public function getById(int $id, bool $lock = false, bool $force = false): ?ScormPackage
    {
        return $this->finder('id', $id)->fetchOne($lock, $force);
    }

    public function findByIdentifier(string $identifier): ?ScormPackage
    {
        return $this->finder('identifier', $identifier)->first();
    }

    public function scopeId(Builder $query, int $id): void
    {
        $query->where('id', '=', $id);
    }

    public function scopeIdentifier(Builder $query, string $identifier): void
    {
        $query->where('identifier', '=', $identifier);
    }

    public function createScos(ScormPackage $package, array $scosData): void
    {
        $package->scos()->createMany($scosData);
    }
}
