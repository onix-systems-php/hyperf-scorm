<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Contract\RequestInterface;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormJobStatus;
use OnixSystemsPHP\HyperfScorm\Service\ScormJobStatusService;
use OpenApi\Attributes as OA;

#[Controller(prefix: 'v1/scorm/jobs')]
class ScormJobStatusController extends AbstractController
{
    public function __construct(
        private readonly ScormJobStatusService $jobStatusService,
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
    public function status(string $jobId): ResourceScormJobStatus
    {
        $progress = $this->jobStatusService->getProgress($jobId);
        $result = $this->jobStatusService->getResult($jobId);

        // Use progress data as primary source, fallback to result if job completed
        $status = $progress ?? $result;

        if ($status === null) {
            throw new \RuntimeException('Job not found or expired', 404);
        }

        // Ensure job_id is included in response
        $status['job_id'] = $jobId;

        return ResourceScormJobStatus::make($status);
    }

    #[OA\Post(
        path: '/v1/scorm/jobs/batch-status',
        operationId: 'getScormJobsBatchStatus',
        summary: 'Get status for multiple SCORM processing jobs',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['job_ids'],
                properties: [
                    new OA\Property(
                        property: 'job_ids',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['job-uuid-1', 'job-uuid-2']
                    ),
                ]
            )
        ),
        tags: ['scorm'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Batch job statuses',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                ref: '#/components/schemas/ResourceScormJobStatus'
                            )
                        ),
                    ],
                    type: 'object'
                )
            ),
        ],
    )]
    public function getBatchStatus(RequestInterface $request): array
    {
        $jobIds = $request->input('job_ids', []);

        if (empty($jobIds) || ! is_array($jobIds)) {
            throw new \InvalidArgumentException('job_ids array is required');
        }

        $statuses = [];
        foreach ($jobIds as $jobId) {
            $progress = $this->jobStatusService->getProgress($jobId);
            $result = $this->jobStatusService->getResult($jobId);

            $status = $progress ?? $result;

            if ($status !== null) {
                $status['job_id'] = $jobId;
                $statuses[$jobId] = $status;
            }
        }

        return ['data' => $statuses];
    }

    #[OA\Post(
        path: '/v1/scorm/jobs/{jobId}/cancel',
        operationId: 'cancelScormJob',
        summary: 'Cancel SCORM processing job',
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
                description: 'Job cancelled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean'),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'job_id', type: 'string'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Job cannot be cancelled (already processing or completed)'
            ),
            new OA\Response(ref: '#/components/responses/404', response: 404),
        ],
    )]
    public function cancelJob(string $jobId): array
    {
        $progress = $this->jobStatusService->getProgress($jobId);
        if ($progress === null) {
            throw new \RuntimeException('Job not found or expired', 404);
        }

        // Check if job can be cancelled (not already processing)
        if ($progress && $progress['status'] === 'processing') {
            return [
                'success' => false,
                'message' => 'Job cannot be cancelled (already processing or completed)',
                'job_id' => $jobId,
            ];
        }

        // Cancel the job by updating status
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

        return [
            'success' => true,
            'message' => 'Job cancelled successfully',
            'job_id' => $jobId,
        ];
    }
}
