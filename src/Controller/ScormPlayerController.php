<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\ResponseInterface;
use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\Service\ScormApi\ScormPlayerService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

class ScormPlayerController extends AbstractController
{
    public function __construct(
        private readonly SessionManager $sessionManager
    ) {
    }

    #[OA\Get(
        path: '/v1/scorm/player/{packageId}/{sessionToken?}',
        operationId: 'launchScormPlayer',
        summary: 'Launch SCORM player with session restoration',
        tags: ['scorm-player'],
        parameters: [
            new OA\Parameter(name: 'packageId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'userId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
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
        int $packageId,
        ?string $sessionToken = null
    ): PsrResponseInterface {
//        $userId = $this->sessionManager->user()?->getId();
        $userId = 1;
        $playerData = $scormPlayerService->run($packageId, $userId, $sessionToken);

        return $response->withHeader('Content-Type', 'text/html')
                       ->withBody(new SwooleStream($playerData->playerHtml));
    }
}
