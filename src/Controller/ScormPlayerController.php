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

    #[OA\Get(//@SONAR_STOP@
        path: '/v1/api/scorm/player/{packageId}/launch/{sessionToken?}',
        operationId: 'launchScormPlayer',
        summary: 'Launch SCORM player',
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
                description: 'Session token to resume existing session',
                in: 'path',
                required: false,
                schema: new OA\Schema(type: 'string', nullable: true)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'SCORM player HTML page',
                content: new OA\MediaType(
                    mediaType: 'text/html',
                    schema: new OA\Schema(type: 'string')
                )
            ),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]//@SONAR_START@
    public function launch(
        ScormPlayerService $scormPlayerService,
        ResponseInterface $response,
        int $packageId,
    ): PsrResponseInterface {
        $playerData = $scormPlayerService->run($packageId);

        return $response->withHeader('Content-Type', 'text/html')
                       ->withBody(new SwooleStream($playerData->playerHtml));
    }
}
