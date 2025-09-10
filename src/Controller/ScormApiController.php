<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\DTO\ScormCommitDTO;
use OnixSystemsPHP\HyperfScorm\Repository\ScormAttemptRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Request\RequestScormCommit;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormCommit;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormInitialize;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\InitializeScormService;
use OnixSystemsPHP\HyperfScorm\Service\ScormCommitService;
use OpenApi\Attributes as OA;

class ScormApiController extends AbstractController
{
    public function __construct(
        private readonly SessionManager $sessionManager
    ) {
    }

    #[OA\Post(
        path: '/v1/scorm-player/session/{sessionToken}/initialize',
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
    public function initialize(
        InitializeScormService $initializeScormService,
        int $packageId,
        string $sessionToken
    ): ResourceScormInitialize {
        $data = $initializeScormService->run($packageId, $sessionToken);
        return ResourceScormInitialize::make($data);
    }

    #[OA\Post(
        path: '/v1/scorm/api/{sessionId}/commit',
        operationId: 'scormApiCommitCompact',
        summary: 'Commit SCORM data in compact format',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RequestScormCompactCommit')
        ),
        tags: ['scorm-api'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Data committed successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ResourceScormCommit')
            ),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function commit(
        RequestScormCommit $request,
        ScormCommitService $service,
        int $packageId,
        string $sessionToken,
    ): ResourceScormCommit {
        $scormCommitDTO = ScormCommitDTO::make([...$request->validated(), 'session_token' => $sessionToken]);
        $result = $service->run($packageId, $scormCommitDTO);
        return ResourceScormCommit::make($result);
    }
}
