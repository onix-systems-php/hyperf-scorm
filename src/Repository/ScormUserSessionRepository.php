<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Repository;

use OnixSystemsPHP\HyperfCore\Model\Builder;
use OnixSystemsPHP\HyperfCore\Repository\AbstractRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormUserSession;

/**
 * @method ScormUserSession create(array $data)
 * @method ScormUserSession update(ScormUserSession $model, array $data)
 * @method ScormUserSession save(ScormUserSession $model)
 * @method bool delete(ScormUserSession $model)
 * @method Builder|ScormUserSessionRepository finder(string $type, ...$parameters)
 * @method null|ScormUserSession fetchOne(bool $lock, bool $force)
 */
class ScormUserSessionRepository extends AbstractRepository
{
    protected string $modelClass = ScormUserSession::class;

    public function findById(int $id, bool $lock = false, bool $force = false): ?ScormUserSession
    {
        return $this->finder('id', $id)->fetchOne($lock, $force);
    }

    public function findByIdentifier(
        int $packageId,
        string $sessionToken,
        bool $lock = false,
        bool $force = false
    ): ?ScormUserSession {
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

    public function createMany(ScormUserSession $session, array $data, string $relation): ScormUserSession
    {
        $session->$relation()->createMany($data);
        return $session;
    }
}
