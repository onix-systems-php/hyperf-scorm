<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Repository;

use OnixSystemsPHP\HyperfCore\Model\Builder;
use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormSession;

/**
 * @method ScormSession create(array $data)
 * @method ScormSession update(ScormSession $model, array $data)
 * @method ScormSession save(ScormSession $model)
 * @method bool delete(ScormSession $model)
 * @method Builder|ScormUserSessionRepository finder(string $type, ...$parameters)
 * @method null|ScormSession fetchOne(bool $lock, bool $force)
 */
class ScormUserSessionRepository extends AbstractRepository
{
    protected string $modelClass = ScormSession::class;

    public function findById(int $id, bool $lock = false, bool $force = false): ?ScormSession
    {
        return $this->finder('id', $id)->fetchOne($lock, $force);
    }

    public function findByProjectAndUser(
        int $packageId,
        int $userId,
        bool $lock = false,
        bool $force = false
    ): ?ScormSession {
        return $this
            ->finder('packageId', $packageId)
            ->finder('userId', $userId)
            ->fetchOne($lock, $force);
    }

    public function findByIdentifier(
        int $packageId,
        string $sessionToken,
        bool $lock = false,
        bool $force = false
    ): ?ScormSession {
        return $this
            ->finder('packageId', $packageId)
            ->finder('sessionToken', $sessionToken)
            ->fetchOne($lock, $force);
    }

    public function scopeId(Builder $query, int $id): void
    {
        $query->where('id', '=', $id);
    }

    public function scopeUserId(Builder $query, int $userId): void
    {
        $query->where('user_id', '=', $userId);
    }

    public function scopePackageId(Builder $query, int $packageId): void
    {
        $query->where('package_id', '=', $packageId);
    }

    public function scopeSessionToken(Builder $query, string $sessionToken): void
    {
        $query->where('session_token', '=', $sessionToken);
    }

    public function createMany(ScormSession $session, array $data, string $relation): ScormSession
    {
        $session->{$relation}()->createMany($data);
        return $session;
    }
}
