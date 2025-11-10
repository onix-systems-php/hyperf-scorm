<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormAsyncJobDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Job\ProcessScormPackageJob;
use Ramsey\Uuid\Uuid;

#[Service]
class ScormAsyncQueueService
{
    public const QUEUE_NAME = 'scorm-processing';

    private const TIME_PER_MB = 2;

    public function __construct(
        private readonly DriverFactory $driverFactory,
        private readonly ScormTempFileService $tempFileService,
        private readonly ScormJobStatusService $jobStatusService,
    ) {
    }

    public function run(ScormUploadDTO $dto): ScormAsyncJobDTO
    {
        $jobId = Uuid::uuid4()->toString();
        $uploadedFile = $this->tempFileService->saveTempFile($dto->file, $jobId);

        $this->jobStatusService->initializeJob($jobId, [
            'job_id' => $jobId,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'queued',
            'file_name' => $dto->file->getClientFilename(),
            'file_size' => $dto->file->getSize(),
            'created_at' => time(),
        ]);

        $driver = $this->driverFactory->get(self::QUEUE_NAME);
        $driver->push(new ProcessScormPackageJob(
            $jobId,
            $uploadedFile,
            $dto->file->getClientFilename(),
            $dto->file->getSize(),
            1, // FIXME: Get actual user ID from authentication context - requires UserContextService implementation
            [
                'title' => $dto->title,
                'description' => $dto->description ?? null,
            ]
        ));

        return ScormAsyncJobDTO::make([
            'job_id' => $jobId,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'queued',
            'estimated_time' => $this->estimateProcessingTime($dto->file->getSize()),
        ]);
    }

    public function cancelJob(string $jobId): bool
    {
        $progress = $this->jobStatusService->getProgress($jobId);
        if ($progress && $progress['status'] === 'processing') {
            return false;
        }

        $this->jobStatusService->updateProgress($jobId, [
            'status' => 'cancelled',
            'progress' => 0,
            'stage' => 'cancelled',
            'cancelled_at' => time(),
        ]);

        $this->jobStatusService->setResult($jobId, [
            'status' => 'cancelled',
            'cancelled_at' => time(),
        ]);

        return true;
    }

    public function getJobProgress(string $jobId): ?array
    {
        return $this->jobStatusService->getProgress($jobId);
    }

    public function getJobResult(string $jobId): ?array
    {
        return $this->jobStatusService->getResult($jobId);
    }

    private function estimateProcessingTime(int $fileSize): int
    {
        $megabytes = $fileSize / (1024 * 1024);
        return (int)($megabytes * self::TIME_PER_MB);
    }
}
