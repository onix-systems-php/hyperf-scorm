<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OnixSystemsPHP\HyperfScorm\Constants\AttemptStatuses;
use OpenApi\Attributes as OA;

#[
    OA\Schema(
        schema: "ResourceScormAttempt",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "package_id", type: "integer"),
            new OA\Property(property: "user_id", type: "integer"),
            new OA\Property(
                property: "status",
                type: "string",
                enum: AttemptStatuses::ALL,
            ),
            new OA\Property(
                property: "lesson_location",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "lesson_status",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "suspend_data",
                type: "string",
                nullable: true,
            ),
            new OA\Property(
                property: "score",
                type: "number",
                format: "float",
                nullable: true,
            ),
            new OA\Property(
                property: "time_spent",
                type: "integer",
                nullable: true,
            ),
            new OA\Property(property: "cmi_data", type: "object"),
            new OA\Property(
                property: "started_at",
                type: "string",
                format: "date-time",
                nullable: true,
            ),
            new OA\Property(
                property: "completed_at",
                type: "string",
                format: "date-time",
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
class ResourceScormAttempt extends AbstractResource
{
    public function toArray(): array
    {
        return [
            "id" => $this->resource->id,
            "package_id" => $this->resource->package_id,
            "user_id" => $this->resource->user_id,
            "status" => $this->resource->status,
            "lesson_location" => $this->resource->lesson_location,
            "lesson_status" => $this->resource->lesson_status,
            "suspend_data" => $this->resource->suspend_data,
            "score" => $this->resource->score,
            "time_spent" => $this->resource->time_spent,
            "cmi_data" => $this->resource->cmi_data,

            "started_at" => $this->resource->started_at,
            "completed_at" => $this->resource->completed_at,
            "created_at" => $this->resource->created_at,
            "updated_at" => $this->resource->updated_at,
        ];
    }
}
