<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class UploadPackageDTO extends AbstractDTO
{

    public string $title;

    public string $description;

    public string|UploadedFile $file;
}
