<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Resource;

use OnixSystemsPHP\HyperfCore\Resource\AbstractResource;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OpenApi\Attributes as OA;

/**
 * @method __construct(ScormPackage $resource)
 * @property OnixSystemsPHP\HyperfScorm\Model\ScormPackage $resource
 */
#[
    OA\Schema(
        schema: "ResourceScormPackage",
        properties: [
            new OA\Property(property: "id", type: "integer"),
            new OA\Property(property: "title", type: "string"),
            new OA\Property(property: "description", type: "string", nullable: true),
            new OA\Property(property: "identifier", type: "string"),
            new OA\Property(
                property: "scorm_version",
                type: "string",
                enum: ["1.2", "2004"],
            ),
            new OA\Property(property: "original_filename", type: "string", nullable: true),
            new OA\Property(property: "file_size", type: "integer", nullable: true),
            new OA\Property(property: "file_size_formatted", type: "string"),
            new OA\Property(property: "file_hash", type: "string", nullable: true),
            new OA\Property(property: "is_active", type: "boolean"),
            new OA\Property(property: "manifest_data", type: "object"),
            new OA\Property(property: "launch_url", type: "string", nullable: true),
            new OA\Property(property: "author", type: "string", nullable: true),
            new OA\Property(property: "mastery_score", type: "number", nullable: true),
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
class ResourceScormPackage extends AbstractResource
{
    public function toArray(): array
    {
        return [
            "id" => $this->resource->id,
            "title" => $this->resource->title,
            "description" => $this->resource->description,
            "identifier" => $this->resource->identifier,
            "scorm_version" => $this->resource->scorm_version,
            "original_filename" => $this->resource->original_filename,
            "file_size" => $this->resource->file_size,
            "file_hash" => $this->resource->file_hash,
            "is_active" => $this->resource->is_active,
            "manifest_data" => $this->resource->manifest_data,
            "launch_url" => $this->resource->getLaunchUrl(),
            "author" => $this->resource->getAuthor(),
            "mastery_score" => $this->resource->getMasteryScore(),
            "created_at" => $this->resource->created_at,
            "updated_at" => $this->resource->updated_at,
        ];
    }
}
