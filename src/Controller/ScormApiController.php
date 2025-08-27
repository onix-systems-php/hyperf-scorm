<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfCore\Resource\ResourceSuccess;
use OnixSystemsPHP\HyperfScorm\Service\ScormTrackingService;
use OnixSystemsPHP\HyperfScorm\Repository\ScormAttemptRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Factory\ScormApiStrategyFactory;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use OpenApi\Attributes as OA;

/**
 * SCORM API Controller - handles SCORM Runtime API calls from JavaScript
 */
class ScormApiController extends AbstractController
{
    public function __construct(
        private readonly ScormTrackingService $trackingService,
        private readonly ScormAttemptRepositoryInterface $attemptRepository,
        private readonly ScormApiStrategyFactory $apiStrategyFactory
    ) {}

    #[OA\Post(
        path: '/v1/scorm/api/{attemptId}/initialize',
        operationId: 'scormApiInitialize',
        summary: 'Initialize SCORM session',
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Session initialized'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function initialize(RequestInterface $request, int $attemptId): ResponseInterface
    {
        try {
            $attempt = $this->attemptRepository->findById($attemptId);
            if (!$attempt) {
                return $this->errorResponse('Attempt not found', 404);
            }

            // Update attempt status if not already started
            if ($attempt->status === 'not_attempted') {
                $attempt->status = 'incomplete';
                $attempt->started_at = now();
                $this->attemptRepository->save($attempt);
            }

            return $this->successResponse([
                'message' => 'SCORM session initialized',
                'attemptId' => $attemptId,
                'status' => $attempt->status
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to initialize session: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/v1/scorm/api/{attemptId}/terminate',
        operationId: 'scormApiTerminate',
        summary: 'Terminate SCORM session',
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Session terminated'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function terminate(RequestInterface $request, int $attemptId): ResponseInterface
    {
        try {
            $attempt = $this->attemptRepository->findById($attemptId);

            // Commit any pending data before terminating
            $this->trackingService->commitData($attempt);

            return ResourceSuccess::make([]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to terminate session: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/v1/scorm/api/{attemptId}/commit',
        operationId: 'scormApiCommit',
        summary: 'Commit SCORM tracking data',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'object'),
                    new OA\Property(property: 'action', type: 'string'),
                    new OA\Property(property: 'parameter', type: 'string'),
                ]
            )
        ),
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Data committed successfully'),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function commit(RequestInterface $request, int $attemptId): ResponseInterface
    {
        //make formRequest and make validation
        try {
            $attempt = $this->attemptRepository->findById($attemptId);
            if (!$attempt) {
                return $this->errorResponse('Attempt not found', 404);
            }

            // Get data from request
            $data = $request->input('data', []);

            // Process and validate the data using strategy pattern
            $package = $attempt->package;
            $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);

            $validatedData = [];
            foreach ($data as $element => $value) {
                if ($apiStrategy->validateElement($element, $value)) {
                    $validatedData[$element] = $value;
                } else {
                    // Log invalid element but don't fail the entire commit
                    error_log("Invalid SCORM element: {$element} = {$value}");
                }
            }

            // Update the attempt with validated data
            $this->trackingService->updateCmiData($attempt, $validatedData);

            return $this->successResponse([
                'message' => 'Data committed successfully',
                'attemptId' => $attemptId,
                'elementsProcessed' => count($validatedData)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to commit data: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/v1/scorm/api/{attemptId}/get-value/{element}',
        operationId: 'scormApiGetValue',
        summary: 'Get SCORM CMI element value',
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'element', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Element value retrieved'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function getValue(RequestInterface $request, int $attemptId, string $element): ResponseInterface
    {
        try {
            $attempt = $this->attemptRepository->findById($attemptId);
            if (!$attempt) {
                return $this->errorResponse('Attempt not found', 404);
            }

            $value = $this->trackingService->getCmiValue($attempt, $element);

            return $this->successResponse([
                'element' => $element,
                'value' => $value,
                'attemptId' => $attemptId
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get value: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/v1/scorm/api/{attemptId}/set-value',
        operationId: 'scormApiSetValue',
        summary: 'Set SCORM CMI element value',
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'element', type: 'string'),
                    new OA\Property(property: 'value', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Value set successfully'),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function setValue(RequestInterface $request, int $attemptId): ResponseInterface
    {
        try {
            $attempt = $this->attemptRepository->findById($attemptId);
            if (!$attempt) {
                return $this->errorResponse('Attempt not found', 404);
            }

            $element = $request->input('element');
            $value = $request->input('value');

            if (empty($element)) {
                return $this->errorResponse('Element name is required', 400);
            }

            // Validate element using strategy pattern
            $package = $attempt->package;
            $apiStrategy = $this->apiStrategyFactory->createForVersion($package->scorm_version);

            if (!$apiStrategy->validateElement($element, $value)) {
                return $this->errorResponse("Invalid element or value: {$element}", 400);
            }

            // Update the CMI data
            $this->trackingService->updateCmiData($attempt, [$element => $value]);

            return $this->successResponse([
                'message' => 'Value set successfully',
                'element' => $element,
                'value' => $value,
                'attemptId' => $attemptId
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to set value: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Get(
        path: '/v1/scorm/api/{attemptId}/status',
        operationId: 'scormApiGetStatus',
        summary: 'Get SCORM attempt status and progress',
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status retrieved'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function getStatus(RequestInterface $request, int $attemptId): ResponseInterface
    {
        try {
            $attempt = $this->attemptRepository->findById($attemptId);
            if (!$attempt) {
                return $this->errorResponse('Attempt not found', 404);
            }

            return $this->successResponse([
                'attemptId' => $attemptId,
                'status' => $attempt->status,
                'lessonStatus' => $attempt->lesson_status,
                'lessonLocation' => $attempt->lesson_location,
                'score' => $attempt->score,
                'timeSpent' => $attempt->time_spent,
                'startedAt' => $attempt->started_at?->toISOString(),
                'completedAt' => $attempt->completed_at?->toISOString(),
                'progress' => $this->calculateProgress($attempt)
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to get status: ' . $e->getMessage(), 500);
        }
    }

    #[OA\Post(
        path: '/v1/scorm/api/{attemptId}/heartbeat',
        operationId: 'scormApiHeartbeat',
        summary: 'Send heartbeat to keep session alive',
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'attemptId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Heartbeat received'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
        ],
    )]
    public function heartbeat(RequestInterface $request, int $attemptId): ResponseInterface
    {
        try {
            $attempt = $this->attemptRepository->findById($attemptId);
            if (!$attempt) {
                return $this->errorResponse('Attempt not found', 404);
            }

            // Update last activity timestamp
            $attempt->updated_at = now();
            $this->attemptRepository->save($attempt);

            return $this->successResponse([
                'message' => 'Heartbeat received',
                'attemptId' => $attemptId,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse('Heartbeat failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Calculate progress percentage
     */
    private function calculateProgress(\OnixSystemsPHP\HyperfScorm\Model\ScormAttempt $attempt): array
    {
        $progress = [
            'percentage' => 0,
            'completed' => false,
            'passed' => false
        ];

        // Basic progress based on status
        switch ($attempt->status) {
            case 'not_attempted':
                $progress['percentage'] = 0;
                break;
            case 'incomplete':
            case 'browsed':
                $progress['percentage'] = 50;
                break;
            case 'completed':
                $progress['percentage'] = 100;
                $progress['completed'] = true;
                break;
            case 'passed':
                $progress['percentage'] = 100;
                $progress['completed'] = true;
                $progress['passed'] = true;
                break;
            case 'failed':
                $progress['percentage'] = 100;
                $progress['completed'] = true;
                $progress['passed'] = false;
                break;
        }

        // Enhanced progress from SCORM 2004 progress_measure if available
        if ($attempt->cmi_data) {
            $cmiData = $attempt->cmi_data->toArray();
            if (isset($cmiData['cmi.progress_measure'])) {
                $progressMeasure = (float) $cmiData['cmi.progress_measure'];
                if ($progressMeasure >= 0 && $progressMeasure <= 1) {
                    $progress['percentage'] = $progressMeasure * 100;
                }
            }
        }

        return $progress;
    }

    /**
     * Success response helper
     */
    private function successResponse(array $data = []): ResponseInterface
    {
        return $this->response->json([
            'success' => true,
            ...$data
        ]);
    }

    /**
     * Error response helper
     */
    private function errorResponse(string $message, int $code = 400): ResponseInterface
    {
        return $this->response->json([
            'success' => false,
            'message' => $message,
            'error_code' => $code
        ], $code);
    }
}
