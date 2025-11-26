<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;

#[Service]
class ScormTempFileService
{
    private const TEMP_DIR = BASE_PATH . '/runtime/scorm-queue-tmp';

    public function __construct()
    {
    }

    public function saveTempFile(UploadedFile $file, string $folder): string
    {
        if (! is_dir($this->getTempDir($folder))) {
            mkdir($this->getTempDir($folder), 0755, true);
        }

        $tempPath = $this->getTempDir($folder) . DIRECTORY_SEPARATOR . time() . '.zip';
        $file->moveTo($tempPath);

        return $tempPath;
    }

    public function getTempDir($folder): string
    {
        return self::TEMP_DIR . DIRECTORY_SEPARATOR . $folder;
    }
}
