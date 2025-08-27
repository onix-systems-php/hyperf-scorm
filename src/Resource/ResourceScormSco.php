<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OnixSystemsPHP\HyperfScorm\Model\ScormSco;
use OpenApi\Attributes as OA;

/**
 * @method __construct(ScormSco $resource)
 * @property OnixSystemsPHP\HyperfScorm\Model\ScormSco $resource
 */
#[
    OA\Schema(
        schema: "ResourceScormSco",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "package_id", type: "integer"),
            new OA\Property(property: "identifier", type: "string"),
            new OA\Property(property: "title", type: "string"),
            new OA\Property(
                property: "launch_url",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "prerequisites",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "parameters",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "mastery_score",
                type: "number",
                format: "float",
                nullable: true,
            ),
            new OA\Property(
                property: "max_time_allowed",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "time_limit_action",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "created_at",
                type: "string",
                format: "date-time",
            ),
            new OA\Property(
                property: "updated_at",
                type: "string",
                format: "date-time",
            ),
        ],
        type: "object",
    ),
]
class ResourceScormSco extends AbstractResource
{
    public function toArray(): array
    {
        return [
            "id" => $this->resource->id,
            "package_id" => $this->resource->package_id,
            "identifier" => $this->resource->identifier,
            "title" => $this->resource->title,
            "launch_url" => $this->resource->launch_url,
            "prerequisites" => $this->resource->prerequisites,
            "parameters" => $this->resource->parameters,
            "mastery_score" => $this->resource->mastery_score,
            "max_time_allowed" => $this->resource->max_time_allowed,
            "time_limit_action" => $this->resource->time_limit_action,
            "created_at" => $this->resource->created_at,
            "updated_at" => $this->resource->updated_at,
        ];
    }
}
