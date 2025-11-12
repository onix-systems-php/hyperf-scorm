<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Scorm\Controller;

use App\Common\Constants\UserRoles;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use OnixSystemsPHP\HyperfAuth\SessionManager;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationRequestDTO;
use OnixSystemsPHP\HyperfCore\Resource\ResourceSuccess;
use OnixSystemsPHP\HyperfPolicy\Annotation\Acl;
use OnixSystemsPHP\HyperfScorm\Contract\Gateway\ScormGatewayInterface;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Request\RequestUploadScormPackage;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormAsyncJob;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackagePaginated;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;


#[Controller(prefix: 'v1/scorm')]
class ScormController extends AbstractController
{
    public function __construct(private readonly ScormGatewayInterface $scormGateway)
    {
    }

    #[OA\Post(
        path: '/v1/scorm/packages/upload',
        operationId: 'uploadScormPackageZip',
        summary: 'Upload SCORM package from ZIP file',
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                        new OA\Property(property: 'title', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                    ]
                )
            )
        ),
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 201, description: 'SCORM package uploaded successfully'),
            new OA\Response(ref: '#/components/responses/400', response: 400),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/422', response: 422),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    #[PostMapping(path: 'packages/upload')]//NOTICE you can use your routing
    #[Acl(UserRoles::GROUP_ADMINS)]//NOTICE you can use your ACL rules
    public function upload(
        RequestUploadScormPackage $request,
    ): ResourceScormAsyncJob {
        $sessionManager = ApplicationContext::getContainer()->get(SessionManager::class);//Notice Use your SessionManager
        $userId = $sessionManager->user()->getId();
        $jobDTO = $this->scormGateway->upload(ScormUploadDTO::make($request), $userId);
        return ResourceScormAsyncJob::make($jobDTO);
    }

    #[OA\Get(// @SONAR_STOP@
        path: '/v1/scorm/packages',
        operationId: 'scormPackagesList',
        summary: 'Get list of packages',
        security: [['bearerAuth' => []]],
        tags: ['scorm'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/Locale'),
            new OA\Parameter(ref: '#/components/parameters/Pagination_page'),
            new OA\Parameter(ref: '#/components/parameters/Pagination_per_page'),
            new OA\Parameter(ref: '#/components/parameters/Pagination_order'),
        ],
        responses: [
            new OA\Response(response: 200, description: '', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', ref: '#/components/schemas/ResourceScormPackagePaginated'),
            ])),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/403', response: 403),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    #[GetMapping(path: 'packages')]//NOTICE you can use your routing
    public function index(RequestInterface $request): ResourceScormPackagePaginated
    {
        $packages = $this->scormGateway->index($request->getQueryParams(), PaginationRequestDTO::make($request));

        return ResourceScormPackagePaginated::make($packages);
    }

    #[OA\Get(// @SONAR_STOP@
        path: '/v1/scorm/player/{packageId}/launch',
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
    )]
    #[GetMapping(path: 'player/{packageId}/launch')]//NOTICE you can use your routing
    #[Acl(UserRoles::GROUP_ALL)]//@SONAR_START@//NOTICE you can use your ACL rules//
    public function launch(
        ResponseInterface $response,
        int $packageId,
        int $userId
    ): PsrResponseInterface {
        $playerData = $this->scormGateway->launch($packageId, $userId);
        return $response->withHeader('Content-Type', 'text/html')
            ->withBody(new SwooleStream($playerData->playerHtml));
    }

    #[OA\Delete(
        path: 'v1/scorm/packages/{id}',
        operationId: 'deleteScormPackage',
        summary: 'Delete SCORM package',
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 204, description: 'SCORM package deleted successfully'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    #[DeleteMapping(path: 'packages/{id}')]
    #[Acl([UserRoles::ADMIN])]//NOTICE you can use your ACL rules
    public function destroy(int $id): ResourceSuccess
    {
        $this->scormGateway->destroy($id);
        return ResourceSuccess::make([]);
    }
}
