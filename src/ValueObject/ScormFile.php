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
    private const REQUIRED_SCORM_FILES = ['imsmanifest.xml'];

    private \ZipArchive $zip;

    private ?string $launchFile;

    private function __construct(
        private readonly string $storage,
        private readonly string $path,
        private readonly string $fullPath,
        private readonly string $extractDir,
    ) {
        $this->zip = $this->openZipArchive($fullPath);
        $this->launchFile = $this->findLaunchFile();
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

    public function findLaunchFile(): ?string
    {
        $xml = new \SimpleXMLElement($this->getManifestFileContents());
        $namespaces = $xml->getNamespaces(true);
        foreach ($xml->resources->resource as $resource) {
            $scormType = (string) $resource->attributes($namespaces['adlcp'])->scormType ?? null;
            if ($scormType === 'sco') {
                return (string) $resource['href'];
            }
        }

        return null;
    }

    public function extract(): bool
    {
        return $this->zip->extractTo($this->getExtractPath());
    }

    public function isValid(): bool
    {
        return collect(self::REQUIRED_SCORM_FILES)
            ->every(fn ($file) => $this->zip->locateName($file, \ZipArchive::FL_NODIR) !== false)
            && $this->launchFile !== null;
    }

    public function toArray(): array
    {
        return [
            'storage' => $this->storage,
            'path' => $this->path,
            'launch_file' => $this->launchFile,
            'full_path' => $this->fullPath,
            'extract_dir' => $this->fullPath,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['storage'],
            $data['path'],
            $data['full_path'],
            $data['extract_dir'],
        );
    }

    private function getManifestFileContents(): string
    {
        $manifest = $this->zip->getFromName('imsmanifest.xml');
        if ($manifest === false) {
            throw new BusinessException(
                ErrorCode::VALIDATION_ERROR,
                __('exceptions.file.scorm_package_missing_manifest')
            );
        }

        return $manifest;
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
