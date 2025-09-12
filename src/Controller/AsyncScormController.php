<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfCore\Resource\ResourceSuccess;
use OnixSystemsPHP\HyperfScorm\Request\RequestUploadScorm;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormProcessingStatus;
use OnixSystemsPHP\HyperfScorm\Service\AsyncScormProcessingService;
use OpenApi\Attributes as OA;

class AsyncScormController extends AbstractController
{
    public function __construct(
        private readonly AsyncScormProcessingService $asyncProcessingService,
        private readonly SessionManager $sessionManager
    ) {
    }

    #[OA\Post(//@SONAR_STOP@
        path: '/v1/scorm/async/upload',
        operationId: 'uploadScormAsync',
        summary: 'Upload SCORM package for async processing',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['scorm_file'],
                    properties: [
                        new OA\Property(
                            property: 'scorm_file',
                            description: 'SCORM package ZIP file',
                            type: 'string',
                            format: 'binary'
                        ),
                        new OA\Property(
                            property: 'metadata',
                            description: 'Optional metadata for the package',
                            type: 'object',
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ),
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'job_id', description: 'Unique job identifier', type: 'string'),
                    new OA\Property(property: 'message', description: 'Success message', type: 'string'),
                ], type: 'object'),
            ])),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/413', response: 413),
            new OA\Response(ref: '#/components/responses/422', response: 422),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]//@SONAR_START@
    public function uploadAsync(RequestUploadScorm $request): ResourceSuccess
    {
        $userId = $this->sessionManager->user()->getId();
        $userId = 1;
        $scormFile = $request->file('scorm_file');
        $metadata = $request->input('metadata', []);

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        $jobId = $this->asyncProcessingService->queueProcessing($scormFile, $userId, $metadata);

        return ResourceSuccess::make([
            'job_id' => $jobId,
            'message' => 'SCORM package queued for processing',
        ]);
    }

    #[OA\Post(//@SONAR_STOP@
        path: '/v1/scorm/async/upload-batch',
        operationId: 'uploadScormBatchAsync',
        summary: 'Upload multiple SCORM packages for batch async processing',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['scorm_files[]'],
                    properties: [
                        new OA\Property(
                            property: 'scorm_files[]',
                            description: 'Array of SCORM package ZIP files (max 3)',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary')
                        ),
                        new OA\Property(
                            property: 'metadata',
                            description: 'Optional metadata for all packages',
                            type: 'object',
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ),
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'job_ids', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'count', type: 'integer'),
                    new OA\Property(property: 'message', type: 'string'),
                ], type: 'object'),
            ])),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/413', response: 413),
            new OA\Response(ref: '#/components/responses/422', response: 422),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]//@SONAR_START@
    public function uploadBatchAsync(RequestInterface $request): ResourceSuccess
    {
        $userId = $this->sessionManager->user()->getId();
        $scormFiles = $request->getUploadedFiles()['scorm_files'] ?? [];
        $metadata = $request->input('metadata', []);

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        $jobIds = $this->asyncProcessingService->queueBatchProcessing($scormFiles, $userId, $metadata);

        return ResourceSuccess::make([
            'job_ids' => $jobIds,
            'count' => count($jobIds),
            'message' => count($jobIds) . ' SCORM packages queued for processing',
        ]);
    }

    #[OA\Get(//@SONAR_STOP@
        path: '/v1/scorm/async/status/{jobId}',
        operationId: 'getScormProcessingStatus',
        summary: 'Get SCORM processing status',
        security: [['bearerAuth' => []]],
        tags: ['scorm'],
        parameters: [
            new OA\Parameter(
                name: 'jobId',
                description: 'Job ID returned from upload',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', ref: '#/components/schemas/ResourceScormProcessingStatus'),
            ])),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]//@SONAR_START@
    public function getStatus(string $jobId): ResourceScormProcessingStatus
    {
        $progress = $this->asyncProcessingService->getProcessingProgress($jobId);
        $result = $this->asyncProcessingService->getProcessingResult($jobId);

//        if (!$progress && !$result) {
//            throw new \Hyperf\HttpServer\Exception\Http\NotFoundException('Job not found');
//        }

        return ResourceScormProcessingStatus::make([
            'job_id' => $jobId,
            'progress' => $progress,
            'result' => $result,
        ]);
    }

    #[OA\Post(//@SONAR_STOP@
        path: '/v1/scorm/async/batch-status',
        operationId: 'getBatchScormProcessingStatus',
        summary: 'Get batch SCORM processing status',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['job_ids'],
                properties: [
                    new OA\Property(
                        property: 'job_ids',
                        description: 'Array of job IDs',
                        type: 'array',
                        items: new OA\Items(type: 'string')
                    ),
                ]
            )
        ),
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'completed', type: 'integer'),
                    new OA\Property(property: 'processing', type: 'integer'),
                    new OA\Property(property: 'failed', type: 'integer'),
                    new OA\Property(property: 'jobs', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'job_id', type: 'string'),
                            new OA\Property(property: 'progress', type: 'object'),
                            new OA\Property(property: 'result', type: 'object'),
                        ],
                        type: 'object'
                    )),
                ], type: 'object'),
            ])),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/422', response: 422),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]//@SONAR_START@
    public function getBatchStatus(RequestInterface $request): ResourceSuccess
    {
        $jobIds = $request->input('job_ids', []);

//        if (empty($jobIds) || !is_array($jobIds)) {
//            throw new \Hyperf\Validation\ValidationException('job_ids field is required and must be an array');
//        }

        $status = $this->asyncProcessingService->getBatchProcessingStatus($jobIds);

        return ResourceSuccess::make($status);
    }

    #[OA\Delete(//@SONAR_STOP@
        path: '/v1/scorm/async/cancel/{jobId}',
        operationId: 'cancelScormProcessing',
        summary: 'Cancel SCORM processing job',
        security: [['bearerAuth' => []]],
        tags: ['scorm'],
        parameters: [
            new OA\Parameter(
                name: 'jobId',
                description: 'Job ID to cancel',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'cancelled', type: 'boolean'),
                    new OA\Property(property: 'message', type: 'string'),
                ], type: 'object'),
            ])),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]//@SONAR_START@
    public function cancelJob(string $jobId): ResourceSuccess
    {
        $cancelled = $this->asyncProcessingService->cancelProcessing($jobId);

        return ResourceSuccess::make([
            'cancelled' => $cancelled,
            'message' => $cancelled
                ? 'Job cancelled successfully'
                : 'Job cannot be cancelled (already processing or completed)',
        ]);
    }
}
