<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use App\Common\Constants\UserRoles;
use Hyperf\HttpServer\Contract\ResponseInterface;
use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfPolicy\Annotation\Acl;
use OnixSystemsPHP\HyperfScorm\Service\ScormPlayerService;
use OnixSystemsPHP\HyperfScorm\Service\ScormTrackingServiceInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;

/**
 * Thin controller for SCORM Player functionality
 */
class ScormPlayerController extends AbstractController
{
    public function __construct(
        private readonly SessionManager $sessionManager
    ) {}

    #[OA\Get(
        path: '/v1/scorm/player/{packageId}',
        operationId: 'launchScormPlayer',
        summary: 'Launch SCORM player with session restoration',
        tags: ['scorm-player'],
        parameters: [
            new OA\Parameter(name: 'packageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', in: 'query', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'SCORM player HTML', content: new OA\MediaType(mediaType: 'text/html')),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
//    #[Acl(roles: [UserRoles::GROUP_ALL])]//@SONAR_START@
    public function launch(
        ScormPlayerService $scormPlayerService,
        ResponseInterface $response,
        int $packageId
    ): PsrResponseInterface {
//        $userId = $this->sessionManager->user()?->getId();
        $userId = 1;

        $playerData = $scormPlayerService->getPlayer($packageId, $userId);

        return $response->withHeader('Content-Type', 'text/html')
                       ->withBody(new SwooleStream($playerData->playerHtml));
    }

//    #[OA\Get(
//        path: '/v1/scorm/player/{packageId}/data',
//        operationId: 'getPlayerData',
//        summary: 'Get SCORM player configuration data as JSON',
//        tags: ['scorm-player'],
//        responses: [
//            new OA\Response(
//                response: 200,
//                description: 'Player configuration data',
//                content: new OA\JsonContent(ref: '#/components/schemas/ScormPlayerDTO')
//            ),
//        ],
//    )]
//    public function getPlayerData(RequestInterface $request, int $packageId): PsrResponseInterface
//    {
//        $userId = (int) $request->input('userId');
//
//        $playerData = $this->playerService->generatePlayer($packageId, $userId);
//
//        return $this->success($playerData);
//    }
//
//    #[OA\Post(
//        path: '/v1/scorm/api/initialize',
//        operationId: 'initializeScormSession',
//        summary: 'Initialize SCORM learning session',
//        tags: ['scorm-api'],
//        requestBody: new OA\RequestBody(
//            required: true,
//            content: new OA\JsonContent(properties: [
//                new OA\Property(property: 'sessionId', type: 'string'),
//                new OA\Property(property: 'action', type: 'string', enum: ['initialize'])
//            ])
//        ),
//        responses: [
//            new OA\Response(response: 200, description: 'Session initialized'),
//        ],
//    )]
//    public function initializeSession(RequestInterface $request): PsrResponseInterface
//    {
//        $sessionId = $request->input('sessionId');
//
//        $result = $this->trackingService->initializeSession($sessionId);
//
//        return $this->success(['initialized' => $result]);
//    }
//
//    #[OA\Post(
//        path: '/v1/scorm/api/set-value',
//        operationId: 'setScormValue',
//        summary: 'Set SCORM tracking value',
//        tags: ['scorm-api'],
//        requestBody: new OA\RequestBody(
//            required: true,
//            content: new OA\JsonContent(properties: [
//                new OA\Property(property: 'sessionId', type: 'string'),
//                new OA\Property(property: 'element', type: 'string'),
//                new OA\Property(property: 'value', type: 'string')
//            ])
//        ),
//        responses: [
//            new OA\Response(response: 200, description: 'Value set successfully'),
//        ],
//    )]
//    public function setValue(RequestInterface $request): PsrResponseInterface
//    {
//        $sessionId = $request->input('sessionId');
//        $element = $request->input('element');
//        $value = $request->input('value');
//
//        $result = $this->trackingService->setValue($sessionId, $element, $value);
//
//        return $this->success(['success' => $result]);
//    }
//
//    #[OA\Post(
//        path: '/v1/scorm/api/commit',
//        operationId: 'commitScormData',
//        summary: 'Commit SCORM tracking data to database',
//        tags: ['scorm-api'],
//        requestBody: new OA\RequestBody(
//            required: true,
//            content: new OA\JsonContent(properties: [
//                new OA\Property(property: 'sessionId', type: 'string'),
//                new OA\Property(property: 'action', type: 'string', enum: ['commit'])
//            ])
//        ),
//        responses: [
//            new OA\Response(response: 200, description: 'Data committed successfully'),
//        ],
//    )]
//    public function commit(RequestInterface $request): PsrResponseInterface
//    {
//        $sessionId = $request->input('sessionId');
//
//        $result = $this->trackingService->commitSession($sessionId);
//
//        return $this->success(['committed' => $result]);
//    }
//
//    #[OA\Post(
//        path: '/v1/scorm/api/terminate',
//        operationId: 'terminateScormSession',
//        summary: 'Terminate SCORM learning session',
//        tags: ['scorm-api'],
//        responses: [
//            new OA\Response(response: 200, description: 'Session terminated'),
//        ],
//    )]
//    public function terminateSession(RequestInterface $request): PsrResponseInterface
//    {
//        $sessionId = $request->input('sessionId');
//
//        $result = $this->trackingService->terminateSession($sessionId);
//
//        return $this->success(['terminated' => $result]);
//    }
}
