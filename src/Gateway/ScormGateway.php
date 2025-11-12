<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Gateway;

use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationRequestDTO;
use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationResultDTO;
use OnixSystemsPHP\HyperfScorm\Contract\Gateway\ScormGatewayInterface;
use OnixSystemsPHP\HyperfScorm\DTO\ScormAsyncJobDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormJobStatus;
use OnixSystemsPHP\HyperfScorm\Service\DeleteScormPackageService;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\ScormPlayerService;
use OnixSystemsPHP\HyperfScorm\Service\ScormAsyncQueueService;
use OnixSystemsPHP\HyperfScorm\Service\ScormJobStatusService;

class ScormGateway implements ScormGatewayInterface
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly ScormAsyncQueueService $scormAsyncQueueService,
        private readonly DeleteScormPackageService $deleteScormPackageService,
        private readonly ScormPlayerService $scormPlayerService,
        private readonly ScormJobStatusService $scormJobStatusService,
    ) {
    }
    public function index(array $filters, PaginationRequestDTO $paginationRequestDTO): PaginationResultDTO
    {
        return $this->scormPackageRepository->getPaginated($filters, $paginationRequestDTO);
    }


    public function upload(ScormUploadDTO $scormUploadDTO, int $userId): ScormAsyncJobDTO
    {
        return $this->scormAsyncQueueService->run($scormUploadDTO, $userId);
    }

    public function destroy(int $packageId): ScormPackage
    {
        return $this->deleteScormPackageService->run($packageId);
    }


    public function launch(int $packageId, int $userId): ScormPlayerDTO
    {
        return $this->scormPlayerService->run($packageId, $userId);
    }

    public function statusJob(string $jobId): array
    {
        $progress = $this->scormJobStatusService->getProgress($jobId);
        $result = $this->scormJobStatusService->getResult($jobId);

        $status = $progress ?? $result;

        if ($status === null) {
            throw new \RuntimeException('Job not found or expired', 404);
        }

        $status['job_id'] = $jobId;
        return $status;
    }

    public function cancelJob(string $jobId): array
    {
        $progress = $this->scormJobStatusService->getProgress($jobId);

        if ($progress === null) {
            throw new \RuntimeException('Job not found or expired', 404);
        }

        if ($progress && $progress['status'] === 'processing') {
            return [
                'success' => false,
                'message' => 'Job cannot be cancelled (already processing or completed)',
                'job_id' => $jobId,
            ];
        }

        $this->scormJobStatusService->updateProgress($jobId, [
            'status' => 'cancelled',
            'progress' => 0,
            'stage' => 'cancelled',
            'cancelled_at' => time(),
        ]);

        $this->scormJobStatusService->setResult($jobId, [
            'status' => 'cancelled',
            'cancelled_at' => time(),
        ]);

        return [
            'success' => true,
            'message' => 'Job cancelled successfully',
            'job_id' => $jobId,
        ];
    }
}
