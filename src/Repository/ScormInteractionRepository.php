<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Repository;

use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormInteraction;

/**
 * @method ScormInteraction create(array $data)
 * @method ScormInteraction update(ScormInteraction $model, array $data)
 * @method ScormInteraction save(ScormInteraction $model)
 * @method bool delete(ScormInteraction $model)
 * @method ScormInteractionRepository finder(string $type, ...$parameters)
 * @method null|ScormInteraction fetchOne(bool $lock, bool $force)
 */
class ScormInteractionRepository extends AbstractRepository
{
    protected string $modelClass = ScormInteraction::class;

    public function findById(int $id, bool $lock = false, bool $force = false): ?ScormInteraction
    {
        return $this->finder('id', $id)->fetchOne($lock, $force);
    }

    public function scopeId($builder, int $id): void
    {
        $builder->where('id', '=', $id);
    }
}
