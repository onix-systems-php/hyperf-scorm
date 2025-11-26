<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormUploadDTO extends AbstractDTO
{
    public UploadedFile $file;

    public string $title;

    public ?string $description = null;

    public ?array $metadata = [];

    public function setFileAttribute(UploadedFile $value)
    {
        $this->file = $value;
    }
}
