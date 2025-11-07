<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractPaginatedResource;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OpenApi\Attributes as OA;

/**
 * @method __construct(ScormPackage $resource)
 * @property ScormPackage $resource
 */
#[
    OA\Schema(
        schema: 'ResourceScormPackage',
        properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'description', type: 'string', nullable: true),
            new OA\Property(property: 'identifier', type: 'string'),
            new OA\Property(
                property: 'scorm_version',
                type: 'string',
                enum: ['1.2', '2004'],
            ),
            new OA\Property(property: 'original_filename', type: 'string', nullable: true),
            new OA\Property(property: 'file_size', type: 'integer', nullable: true),
            new OA\Property(property: 'file_size_formatted', type: 'string'),
            new OA\Property(property: 'file_hash', type: 'string', nullable: true),
            new OA\Property(property: 'is_active', type: 'boolean'),
            new OA\Property(property: 'manifest_data', type: 'object'),
            new OA\Property(property: 'launch_url', type: 'string', nullable: true),
            new OA\Property(property: 'author', type: 'string', nullable: true),
            new OA\Property(property: 'mastery_score', type: 'number', nullable: true),
            new OA\Property(
                property: 'created_at',
                type: 'string',
                format: 'date-time',
            ),
            new OA\Property(
                property: 'updated_at',
                type: 'string',
                format: 'date-time',
            ),
        ],
        type: 'object',
    ),
]
class ResourceScormPackagePaginated extends AbstractPaginatedResource
{
    public function toArray(): array
    {
        $result = parent::toArray();
        $result['list'] = ResourceScormPackage::collection($this->resource->list);
        return $result;
    }
}
