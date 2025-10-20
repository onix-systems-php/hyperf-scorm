<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO representing a processed SCORM package
 * Contains information about extracted and uploaded SCORM content
 */
class ProcessedScormPackageDTO extends AbstractDTO
{
    /**
     * Parsed manifest data
     */
    public ScormManifestDTO $manifestData;

    /**
     * Storage path where content was uploaded
     */
    public string $contentPath;

    /**
     * Temporary extraction path (for cleanup)
     */
    public string $extractPath;

    /**
     * Temporary directory (for cleanup)
     */
    public string $tempDir;

    /**
     * Cleanup temporary files and directories
     */
    public function cleanup(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectoryRecursively($this->tempDir);
        }
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

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
