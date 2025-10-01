<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class UploadScormDTO extends AbstractDTO
{
    public UploadedFile $scormFile;
    public int $userId;
    public array $metadata;

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'original_filename' => $this->scormFile->getClientFilename(),
            'file_size' => $this->scormFile->getSize(),
            'metadata' => $this->metadata,
        ];
    }
}
