<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Entity;

use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;

/**
 * Entity representing a processed SCORM package result
 * Contains all data and utilities for working with successfully processed SCORM package
 */
class ProcessedScormPackage
{
    public function __construct(
        public readonly ScormManifestDTO $manifestData,
        public readonly string $contentPath,
        public readonly string $extractPath,
        public readonly string $tempDir
    ) {

    }

    /**
     * Clean up temporary files and directories
     */
    public function cleanup(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectoryRecursively($this->tempDir);
        }
    }

    /**
     * Check if temporary directory cleanup is required
     */
    public function isCleanupRequired(): bool
    {
        return is_dir($this->tempDir);
    }

    /**
     * Get size of temporary directory in bytes
     */
    public function getTempSize(): int
    {
        if (!is_dir($this->tempDir)) {
            return 0;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        // PHP 8.4: Use array_sum with array comprehension-like approach
        return array_sum(
            array_map(
                static fn(\SplFileInfo $file): int => $file->isFile() ? $file->getSize() : 0,
                iterator_to_array($iterator)
            )
        );
    }

    /**
     * Check if manifest data is valid
     */
    public function hasValidManifest(): bool
    {
        // PHP 8.4: More elegant boolean expression with null coalescing
        return ($this->manifestData->identifier ?? '') !== '' &&
               ($this->manifestData->title ?? '') !== '' &&
               !empty($this->manifestData->scos);
    }

    /**
     * Get SCORM version from manifest
     */
    public function getScormVersion(): string
    {
        return $this->manifestData->version->value;
    }

    /**
     * Get package identifier
     */
    public function getPackageIdentifier(): string
    {
        return $this->manifestData->identifier;
    }

    /**
     * Get package title
     */
    public function getPackageTitle(): string
    {
        return $this->manifestData->title;
    }

    /**
     * Check if package is multi-SCO
     */
    public function isMultiSco(): bool
    {
        return $this->manifestData->isMultiSco();
    }

    /**
     * Get SCO items from manifest
     */
    public function getScoItems(): array
    {
        return $this->manifestData->getScos();
    }

    /**
     * Get launch URL for first SCO
     */
    public function getPrimaryLaunchUrl(): ?string
    {
        return $this->manifestData->getPrimaryLaunchUrl();
    }

    /**
     * Get total number of SCOs in package
     */
    public function getScoCount(): int
    {
        return count($this->getScoItems());
    }

    /**
     * Get total number of resources in package (same as SCO count now)
     */
    public function getResourceCount(): int
    {
        return count($this->manifestData->scos);
    }

    /**
     * Check if specific directory exists in temp folder
     */
    public function hasExtractedFile(string $relativePath): bool
    {
        $fullPath = $this->extractPath . DIRECTORY_SEPARATOR . $relativePath;
        return file_exists($fullPath);
    }

    /**
     * Get full path to extracted file
     */
    public function getExtractedFilePath(string $relativePath): string
    {
        return $this->extractPath . DIRECTORY_SEPARATOR . $relativePath;
    }
    public function getManifestPath(): string
    {
        return $this->extractPath . DIRECTORY_SEPARATOR . 'imsmanifest.xml';
    }

    /**
     * Convert entity to array for debugging/logging
     */
    public function toArray(): array
    {
        return [
//            'package_identifier' => $this->getPackageIdentifier(),
            'package_title' => $this->getPackageTitle(),
            'scorm_version' => $this->getScormVersion(),
            'manifest_path' => $this->getManifestPath(),
            'content_path' => $this->contentPath,
            'extract_path' => $this->extractPath,
            'temp_dir' => $this->tempDir,
            'is_multi_sco' => $this->isMultiSco(),
            'sco_count' => $this->getScoCount(),
            'resource_count' => $this->getResourceCount(),
            'temp_size_bytes' => $this->getTempSize(),
            'cleanup_required' => $this->isCleanupRequired(),
            'has_valid_manifest' => $this->hasValidManifest(),
            'primary_launch_url' => $this->getPrimaryLaunchUrl(),
        ];
    }

    /**
     * Remove directory and all contents recursively
     */
    private function removeDirectoryRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        // PHP 8.4: Use match expression for cleaner conditional logic
        foreach ($iterator as $file) {
            match ($file->isDir()) {
                true => rmdir($file->getPathname()),
                false => unlink($file->getPathname()),
            };
        }

        rmdir($dir);
    }
}
