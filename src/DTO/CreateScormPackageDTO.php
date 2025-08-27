<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for creating SCORM package
 */
class CreateScormPackageDTO extends AbstractDTO
{
    public string $title;
    public string $identifier;
    public string $contentPath;
    public ?string $version = null;
    public array $manifestData;
}
