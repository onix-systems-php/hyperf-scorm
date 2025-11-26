<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Model\Filter;

use Hyperf\Stringable\Str;
use OnixSystemsPHP\HyperfCore\Model\Filter\AbstractFilter;
use OpenApi\Attributes as OA;

#[OA\Parameter(
    parameter: 'ScormPackageFilter__title',
    name: 'title',
    in: 'query',
    schema: new OA\Schema(type: 'string'),
    example: 'SomeTeam'
)]
#[OA\Parameter(
    parameter: 'ScormPackageFilter__is_active',
    name: 'is_active',
    in: 'query',
    schema: new OA\Schema(type: 'string'),
)]
class ScormPackageFilter extends AbstractFilter
{
    public function title(string $param): void
    {
        $like = '%' . Str::lower($param) . '%';
        $this->builder->whereRaw('LOWER(title) LIKE ?', [$like]);
    }

    public function isActive(): void
    {
        $this->builder->where(['is_active', true]);
    }
}
