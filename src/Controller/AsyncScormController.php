<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use mysql_xdevapi\Exception;
use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfCore\Resource\ResourceSuccess;
use OnixSystemsPHP\HyperfScorm\Request\RequestUploadScorm;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormProcessingStatus;
use OnixSystemsPHP\HyperfScorm\Service\AsyncScormProcessingService;
use OnixSystemsPHP\HyperfScorm\Service\UploadScormService;
use OnixSystemsPHP\HyperfScorm\DTO\UploadScormDTO;
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
    public function uploadAsync(RequestUploadScorm $request, UploadScormService $service): ResourceSuccess
    {
//        $userId = $this->sessionManager->user()->getId();
        $userId = 1;
        $scormFile = $request->file('scorm_file');
        $metadata = $request->input('metadata', []);

        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true) ?: [];
        }

        $uploadDTO = UploadScormDTO::make([
            'userId' => $userId,
            'scormFile' => $scormFile,
            'metadata' => $metadata,
        ]);
        $result = $service->run($uploadDTO);

        return ResourceSuccess::make($result);
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

        if (!$progress && !$result) {
            throw new Exception('Job not found');
        }

        return ResourceScormProcessingStatus::make([
            'job_id' => $jobId,
            'progress' => $progress,
            'result' => $result,
        ]);
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
