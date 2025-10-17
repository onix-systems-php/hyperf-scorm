<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\AsyncQueue\Driver\DriverFactory;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\ScormAsyncJobDTO;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Job\ProcessScormPackageJob;

#[Service]
class AsyncScormUploadService
{
    public function __construct(
        private readonly DriverFactory $driverFactory,
        private readonly ScormJobIdGenerator $jobIdGenerator,
        private readonly ScormTempFileService $tempFileService,
        private readonly ScormJobStatusService $jobStatusService,
    ) {}

    public function run(UploadPackageDTO $dto): ScormAsyncJobDTO
    {
        $jobId = $this->jobIdGenerator->generate();//move to package and use Uuid::uuid4()->toString()

        $tempPath = $this->tempFileService->saveTempFile($dto->file);

        $this->jobStatusService->initializeJob($jobId, [
            'job_id' => $jobId,
            'status' => 'queued',
            'progress' => 0,
            'stage' => 'queued',
            'file_name' => $dto->file->getClientFilename(),
            'file_size' => $dto->file->getSize(),
            'created_at' => time(),
        ]);

        $driver = $this->driverFactory->get('scorm-processing');
        $driver->push(new ProcessScormPackageJob(
            $jobId,
            $tempPath,
            $dto->file->getClientFilename(),
            $dto->file->getSize(),
            1, // TODO: Get actual user ID from context
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

    private function estimateProcessingTime(int $fileSize): int
    {
        // Примерная оценка: 1MB = 2 секунды обработки
        $megabytes = $fileSize / (1024 * 1024);
        return (int) ($megabytes * 2);
    }
}
