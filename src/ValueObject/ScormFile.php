<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\ValueObject;

use OnixSystemsPHP\HyperfCore\Constants\ErrorCode;
use OnixSystemsPHP\HyperfCore\Exception\BusinessException;
use function Hyperf\Collection\collect;
use function Hyperf\Translation\__;

class ScormFile
{
    public const MANIFEST_FILENAME = 'imsmanifest.xml';

    private const REQUIRED_SCORM_FILES = [self::MANIFEST_FILENAME];

    private \ZipArchive $zip;


    private function __construct(
        private readonly string $storage,
        private readonly string $path,
        private readonly string $fullPath,
        private readonly string $extractDir,
    ) {
        $this->zip = $this->openZipArchive($fullPath);
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    public function getExtractDir(): string
    {
        return $this->extractDir;
    }

    public function getExtractPath(): string
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->extractDir;
    }

    public function extract(): void
    {
        $result  = $this->zip->extractTo($this->getExtractPath());
        if (! $result) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.scorm.package_extract_issue')
            );
        }
    }

    public function isValid(): void
    {
        $isValid = collect(self::REQUIRED_SCORM_FILES)
            ->every(fn ($file) => $this->zip->locateName($file, \ZipArchive::FL_NODIR) !== false);

        if (!$isValid) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.scorm.package_manifest_issue')
            );
        }
    }

    public function getManifestPath(): string
    {
        return $this->getExtractPath() . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
    }

    public function toArray(): array
    {
        return [
            'storage' => $this->storage,
            'path' => $this->path,
            'full_path' => $this->fullPath,
            'extract_dir' => $this->fullPath,
        ];
    }

    public static function fromArray(array $data): self
    {
        $scorm = new self(
            $data['storage'],
            $data['path'],
            $data['full_path'],
            $data['extract_dir'],
        );

        $scorm->isValid();

        return $scorm;
    }

    private function openZipArchive(string $fullPath): \ZipArchive
    {
        $zip = new \ZipArchive();
        if ($zip->open($fullPath) !== true) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.file.scorn_package_issue')
            );
        }

        return $zip;
    }
}
