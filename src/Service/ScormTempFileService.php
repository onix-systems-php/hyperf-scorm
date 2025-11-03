<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\HttpMessage\Upload\UploadedFile;
use OnixSystemsPHP\HyperfCore\Service\Service;
use Psr\Log\LoggerInterface;
use Throwable;

#[Service]
class ScormTempFileService
{
    private const TEMP_DIR = BASE_PATH . '/runtime/scorm-temp';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function saveTempFile(UploadedFile $file): string
    {
        if (!is_dir($this->getTempDir())) {
            mkdir($this->getTempDir(), 0755, true);
        }

        $tempPath = $this->getTempDir() . '/' . time() . '.zip';
        $file->moveTo($tempPath);

        return $tempPath;
    }

    public function cleanup(string $path, ?string $jobId = null): void
    {
        try {
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

    public function getTempDir(): string
    {
        return self::TEMP_DIR;
    }
}
