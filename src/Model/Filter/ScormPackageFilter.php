<?php
declare(strict_types=1);

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
    public function name(string $param): void
    {
        $this->builder->whereRaw('LOWER(name) = ?', [Str::lower($param)]);
    }

    public function isActive(): void
    {
        $this->builder->where(['is_active', true]);
    }
}
