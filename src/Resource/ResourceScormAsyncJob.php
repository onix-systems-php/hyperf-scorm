<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OnixSystemsPHP\HyperfScorm\DTO\ScormAsyncJobDTO;
use OpenApi\Attributes as OA;

/**
 * @method __construct(ScormAsyncJobDTO $resource)
 * @property ScormAsyncJobDTO $resource
 */
#[OA\Schema(
    schema: 'ResourceScormAsyncJob',
    properties: [
        new OA\Property(property: 'job_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['queued', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'progress', type: 'integer', minimum: 0, maximum: 100),
        new OA\Property(property: 'stage', type: 'string'),
        new OA\Property(property: 'estimated_time', type: 'integer', description: 'Estimated time in seconds'),
        new OA\Property(property: 'is_async', type: 'boolean', description: 'Flag indicating async upload'),
    ]
)]
class ResourceScormAsyncJob extends AbstractResource
{
    public function toArray(): array
    {
        return [
            'job_id' => $this->resource->job_id,
            'status' => $this->resource->status,
            'progress' => $this->resource->progress,
            'stage' => $this->resource->stage,
            'estimated_time' => $this->resource->estimated_time,
            'is_async' => true, // Frontend detection flag
        ];
    }
}
