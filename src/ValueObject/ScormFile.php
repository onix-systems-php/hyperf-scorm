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

final class ScormFile
{
    public const MANIFEST_FILENAME = 'imsmanifest.xml';
    public const EXTRACT_FOLDER = 'extract-folder';

    private const REQUIRED_SCORM_FILES = [self::MANIFEST_FILENAME];

    private \ZipArchive $zip;


    private function __construct(
        private readonly string $storage,
        private readonly string $filePath,
        private readonly string $extractBasePath,
        private readonly string $extractDir,
    ) {
        $this->zip = $this->openZipArchive($filePath);
    }

    public function __destruct()
    {
        $this->zip->close();
    }


    public function extract(): void
    {
        $result = $this->zip->extractTo($this->getExtractTo());

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
        return $this->getBasePath() . DIRECTORY_SEPARATOR . self::MANIFEST_FILENAME;
    }

    public function getExtractDir(): string
    {
        return $this->extractDir;
    }
    public function getExtractedBaseDir(): string
    {
        $parts = [
            $this->extractDir,
            self::EXTRACT_FOLDER,
            $this->getArchivePath(),
        ];

        return implode(DIRECTORY_SEPARATOR, array_filter($parts));
    }


    public function toArray(): array
    {
        return [
            'storage' => $this->storage,
            'file_path' => $this->filePath,
            'extract_dir' => $this->extractDir,
            'extract_base_path' => $this->extractBasePath,
        ];
    }

    public static function fromArray(array $data): self
    {
        $scorm = new self(
            $data['storage'],
            $data['file_path'],
            $data['extract_base_path'],
            $data['extract_dir'],
        );

        $scorm->isValid();

        return $scorm;
    }

    private function openZipArchive(string $filePath): \ZipArchive
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.file.scorn_package_issue')
            );
        }

        return $zip;
    }

    private function getExtractTo(): string
    {
        $parts = [
            $this->extractBasePath,
            $this->extractDir . DIRECTORY_SEPARATOR . self::EXTRACT_FOLDER,
        ];

        return implode(DIRECTORY_SEPARATOR, array_filter($parts));
    }

    private function getBasePath(): string
    {
        $parts = [
            $this->getExtractTo(),
            $this->getArchivePath(),
        ];

        return implode(DIRECTORY_SEPARATOR, array_filter($parts));
    }


    private function getArchivePath(): string
    {
        $manifestIndex = $this->zip->locateName(self::MANIFEST_FILENAME, \ZipArchive::FL_NODIR);
        $dir = dirname($this->zip->getNameIndex($manifestIndex));

        return $dir !== '.' && $dir !== '' ? $dir : '';
    }
}
