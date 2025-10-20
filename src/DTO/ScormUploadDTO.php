<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for SCORM package upload operations
 * Unified DTO replacing legacy UploadPackageDTO and UploadScormDTO
 */
class ScormUploadDTO extends AbstractDTO
{
    public string|UploadedFile $file;

    public string $title;

    public ?string $description = null;

    public ?array $metadata = [];

    public ?int $userId = null;
}
