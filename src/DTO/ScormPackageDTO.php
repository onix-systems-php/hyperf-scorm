<?php

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

class ScormPackageDTO extends AbstractDTO
{
    public int $id;
    public string $title;
    public string $version;
    public ?string $description;
    public string $original_filename;
    public ?string $file_size;
    public ?string $file_hash;
    public string $domain;
    public string $launch_url;
    public string $content_path;
    public bool $is_active;
    public string $created_at;
    public string $updated_at;
}
