<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCommitDTO;
use OnixSystemsPHP\HyperfScorm\Request\RequestCommitScorm;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormCommit;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormInitialize;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\InitializeScormService;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\ScormCommitService;
use OpenApi\Attributes as OA;

class ScormApiController extends AbstractController
{
    public function __construct(
        private readonly SessionManager $sessionManager
    ) {

    }

    #[OA\Get(// @SONAR_STOP@
        path: '/v1/api/scorm/{packageId}/initialize',
        operationId: 'initializeScormSession',
        summary: 'Initialize SCORM session',
        security: [['bearerAuth' => []]],
        tags: ['scorm'],
        parameters: [
            new OA\Parameter(
                name: 'packageId',
                description: 'SCORM Package ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'sessionToken',
                description: 'SCORM session token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', ref: '#/components/schemas/ResourceScormInitialize'),
            ])),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )] // @SONAR_START@
    public function initialize(
        InitializeScormService $initializeScormService,
        int $packageId,
    ): ResourceScormInitialize {
        //        $userId = $this->sessionManager?->user()->getId() ?? null;
        $userId = 1;
        return ResourceScormInitialize::make($initializeScormService->run($packageId, $userId));
    }

    #[OA\Post(// @SONAR_STOP@
        path: '/v1/api/scorm/{packageId}/commit/{sessionToken}',
        operationId: 'commitScormProgress',
        summary: 'Commit SCORM progress',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            ref: '#/components/schemas/RequestScormCommit'
        )),
        tags: ['scorm'],
        parameters: [
            new OA\Parameter(
                name: 'packageId',
                description: 'SCORM Package ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'sessionToken',
                description: 'SCORM session token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'string'),
                new OA\Property(property: 'data', ref: '#/components/schemas/ResourceScormCommit'),
            ])),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/422', response: 422),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )] // @SONAR_START@
    public function commit(
        RequestCommitScorm $request,
        ScormCommitService $service,
        int $packageId,
        string $sessionToken,
    ): ResourceScormCommit {
        $scormCommitDTO = ScormCommitDTO::make([...$request->validated(), 'session_token' => $sessionToken]);
        $result = $service->run($packageId, $scormCommitDTO);
        return ResourceScormCommit::make($result);
    }
}
