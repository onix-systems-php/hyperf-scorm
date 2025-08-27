<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\Service\StartScormAttemptService;
use OnixSystemsPHP\HyperfScorm\Service\ScormAttemptService;
use OnixSystemsPHP\HyperfScorm\Resource\ScormAttemptResource;
use OnixSystemsPHP\HyperfScorm\Request\RequestStartScormAttempt;
use OnixSystemsPHP\HyperfScorm\Request\RequestSetCmiValue;
use OnixSystemsPHP\HyperfScorm\DTO\Factory\StartScormAttemptDTOFactory;
use Psr\Http\Message\ResponseInterface;
use OpenApi\Attributes as OA;

class ScormAttemptController extends AbstractController
{
    public function __construct(
        private readonly StartScormAttemptService $startAttemptService,
        private readonly ScormAttemptService $attemptService
    ) {}

    #[OA\Post(
        path: '/v1/scorm/sessions/start',
        operationId: 'startScormAttempt',
        summary: 'Start or resume SCORM attempt',
        tags: ['scorm-attempt']
    )]
    public function start(RequestStartScormAttempt $request): ScormAttemptResource
    {
        $attempt = $this->startAttemptService->run(
            StartScormAttemptDTOFactory::make($request)
        );

        return ScormAttemptResource::make($attempt);
    }

    #[OA\Get(
        path: '/v1/scorm/attempts/{attemptId}',
        operationId: 'getScormAttempt',
        summary: 'Get SCORM attempt details',
        tags: ['scorm-attempt']
    )]
    public function show(RequestInterface $request, int $attemptId): ResponseInterface
    {
        $attempt = $this->attemptService->getAttempt($attemptId);

        if (!$attempt) {
            return $this->response->json([
                'success' => false,
                'message' => 'Attempt not found'
            ], 404);
        }

        return $this->response->json([
            'success' => true,
            'data' => ScormAttemptResource::make($attempt)
        ]);
    }

    #[OA\Post(
        path: '/v1/scorm/attempts/{attemptId}/suspend',
        operationId: 'suspendScormAttempt',
        summary: 'Suspend SCORM attempt',
        tags: ['scorm-attempt']
    )]
    public function suspend(RequestInterface $request, int $attemptId): ResponseInterface
    {
        $data = $request->getParsedBody();
        $suspendData = $data['suspend_data'] ?? '';

        $suspended = $this->attemptService->suspendAttempt($attemptId, $suspendData);

        if (!$suspended) {
            return $this->response->json([
                'success' => false,
                'message' => 'Attempt not found'
            ], 404);
        }

        return $this->response->json([
            'success' => true,
            'message' => 'Attempt suspended'
        ]);
    }

    #[OA\Post(
        path: '/v1/scorm/attempts/{attemptId}/complete',
        operationId: 'completeScormAttempt',
        summary: 'Complete SCORM attempt',
        tags: ['scorm-attempt']
    )]
    public function complete(RequestInterface $request, int $attemptId): ResponseInterface
    {
        $data = $request->getParsedBody();
        $finalScore = $data['final_score'] ?? null;

        $completed = $this->attemptService->completeAttempt($attemptId, $finalScore);

        if (!$completed) {
            return $this->response->json([
                'success' => false,
                'message' => 'Attempt not found'
            ], 404);
        }

        return $this->response->json([
            'success' => true,
            'message' => 'Attempt completed'
        ]);
    }

    #[OA\Get(
        path: '/v1/scorm/attempts/{attemptId}/cmi/{element}',
        operationId: 'setCmiValue',
        summary: 'Set CMI element value',
        tags: ['scorm-attempt']
    )]
    public function setCmi(RequestInterface $request, int $attemptId, string $element): ResponseInterface
    {
        $data = $request->getParsedBody();
        $value = $data['value'] ?? null;

        $updated = $this->attemptService->setCmiValue($attemptId, $element, $value);

        if (!$updated) {
            return $this->response->json([
                'success' => false,
                'message' => 'Attempt not found'
            ], 404);
        }

        return $this->response->json([
            'success' => true,
            'message' => 'CMI value updated'
        ]);
    }

    #[OA\Get(
        path: '/v1/scorm/attempts/{attemptId}/cmi/{element}',
        operationId: 'getCmiValue',
        summary: 'Get CMI element value',
        tags: ['scorm-attempt']
    )]
    public function getCmi(RequestInterface $request, int $attemptId, string $element): ResponseInterface
    {
        $value = $this->attemptService->getCmiValue($attemptId, $element);

        return $this->response->json([
            'success' => true,
            'data' => [
                'element' => $element,
                'value' => $value
            ]
        ]);
    }

    #[OA\Get(
        path: '/v1/scorm/attempts/{attemptId}/cmi',
        operationId: 'getAllCmiData',
        summary: 'Get all CMI data',
        tags: ['scorm-attempt']
    )]
    public function getAllCmi(RequestInterface $request, int $attemptId): ResponseInterface
    {
        $cmiData = $this->attemptService->getCmiDataArray($attemptId);

        return $this->response->json([
            'success' => true,
            'data' => $cmiData
        ]);
    }

    #[OA\Post(
        path: '/v1/scorm/attempts/{attemptId}/cmi/batch',
        operationId: 'setBatchCmiValues',
        summary: 'Set multiple CMI values at once',
        tags: ['scorm-attempt']
    )]
    public function setBatchCmi(RequestInterface $request, int $attemptId): ResponseInterface
    {
        $data = $request->getParsedBody();
        $cmiValues = $data['cmi_values'] ?? [];

        if (empty($cmiValues)) {
            return $this->response->json([
                'success' => false,
                'message' => 'cmi_values array is required'
            ], 400);
        }

        $updated = $this->attemptService->setCmiValues($attemptId, $cmiValues);

        if (!$updated) {
            return $this->response->json([
                'success' => false,
                'message' => 'Attempt not found'
            ], 404);
        }

        return $this->response->json([
            'success' => true,
            'message' => 'CMI values updated successfully'
        ]);
    }

    #[OA\Post(
        path: '/v1/scorm/attempts/{attemptId}/interactions',
        operationId: 'addInteraction',
        summary: 'Add interaction data',
        tags: ['scorm-attempt']
    )]
    public function addInteraction(RequestInterface $request, int $attemptId): ResponseInterface
    {
        $data = $request->getParsedBody();

        $interaction = [
            'id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
            'student_response' => $data['student_response'] ?? null,
            'result' => $data['result'] ?? null,
        ];

        $added = $this->attemptService->addInteraction($attemptId, $interaction);

        if (!$added) {
            return $this->response->json([
                'success' => false,
                'message' => 'Attempt not found'
            ], 404);
        }

        return $this->response->json([
            'success' => true,
            'message' => 'Interaction added successfully'
        ]);
    }
}
