<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormAsyncJob;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackage;

#[Service]
class SmartScormUploadService
{
    // 25MB threshold - based on analysis showing 60s timeout risk
    private const SYNC_THRESHOLD = 25 * 1024 * 1024;

    public function __construct(
        private readonly UploadScormPackageService $syncUpload,
        private readonly AsyncScormUploadService $asyncUpload,
    ) {}

    public function run(UploadPackageDTO $dto): ResourceScormPackage|ResourceScormAsyncJob
    {
        $fileSize = $dto->file->getSize();

//        if ($fileSize < self::SYNC_THRESHOLD) {
//            return ResourceScormPackage::make($this->syncUpload->run($dto));
//        }

        return ResourceScormAsyncJob::make($this->asyncUpload->run($dto));
    }

    public function willUseAsync(int $fileSize): bool
    {
        return $fileSize >= self::SYNC_THRESHOLD;
    }
}
