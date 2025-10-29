<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for managing temporary SCORM files
 * Handles temp file creation, storage, and cleanup
 */
#[Service]
class ScormTempFileService
{
    private const TEMP_DIR = BASE_PATH . '/runtime/scorm-temp';

    public function __construct(
        private readonly ScormJobIdGenerator $jobIdGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Save uploaded file to temporary location
     */
    public function saveTempFile(UploadedFile $file): string
    {
        if (!is_dir($this->getTempDir())) {
            mkdir($this->getTempDir(), 0755, true);
        }

        $tempPath = $this->getTempDir() . '/' . $this->jobIdGenerator->generate() . '.zip';
        $file->moveTo($tempPath);

        return $tempPath;
    }

    /**
     * Cleanup temporary file
     * Note: Only cleans up files in /tmp/ directory for safety
     */
    public function cleanup(string $path, ?string $jobId = null): void
    {
        try {
            // Only cleanup files in temp directories for safety
            if (file_exists($path) && str_contains($path, '/tmp/')) {
                unlink($path);

                if ($jobId) {
                    $this->logger->info('Temporary file cleaned up', [
                        'job_id' => $jobId,
                        'temp_path' => $path,
                    ]);
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to cleanup temporary file', [
                'job_id' => $jobId,
                'temp_path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get temporary directory path
     */
    public function getTempDir(): string
    {
        return self::TEMP_DIR;
    }
}
