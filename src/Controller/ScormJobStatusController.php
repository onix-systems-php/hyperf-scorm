<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormJobStatus;
use OnixSystemsPHP\HyperfScorm\Service\AsyncScormProcessingService;
use OpenApi\Attributes as OA;

#[Controller(prefix: 'v1/scorm/jobs')]
class ScormJobStatusController extends AbstractController
{
    public function __construct(
        private readonly AsyncScormProcessingService $processingService,
    ) {}

    #[OA\Get(
        path: '/v1/scorm/jobs/{jobId}/status',
        operationId: 'getScormJobStatus',
        summary: 'Get SCORM processing job status',
        tags: ['scorm'],
        parameters: [
            new OA\Parameter(
                name: 'jobId',
                description: 'Job UUID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Job status',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ResourceScormJobStatus'
                )
            ),
            new OA\Response(ref: '#/components/responses/404', response: 404),
        ],
    )]
//    #[GetMapping(path: '{jobId}/status')]
    public function status(string $jobId): ResourceScormJobStatus
    {
        $progress = $this->processingService->getProcessingProgress($jobId);
        $result = $this->processingService->getProcessingResult($jobId);

        // Use progress data as primary source, fallback to result if job completed
        $status = $progress ?? $result;

        if ($status === null) {
            throw new \RuntimeException('Job not found or expired', 404);
        }

        // Ensure job_id is included in response
        $status['job_id'] = $jobId;

        return ResourceScormJobStatus::make($status);
    }
}
