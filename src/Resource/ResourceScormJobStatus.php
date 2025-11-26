<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ResourceScormJobStatus',
    properties: [
        new OA\Property(property: 'job_id', type: 'string'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'progress', type: 'integer'),
        new OA\Property(property: 'stage', type: 'string'),
        new OA\Property(property: 'package_id', type: 'integer', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
    ]
)]
class ResourceScormJobStatus extends AbstractResource
{
    public function toArray(): array
    {
        return [
            'job_id' => $this->resource['job_id'],
            'status' => $this->resource['status'],
            'progress' => $this->resource['progress'],
            'stage' => $this->resource['stage'],
            'package_id' => $this->resource['package_id'] ?? null,
            'error' => $this->resource['error'] ?? null,
        ];
    }
}
