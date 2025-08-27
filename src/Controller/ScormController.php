<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\DTO\Factory\CreateScormPackageDTOFactory;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Request\RequestCreateScormPackage;
use OnixSystemsPHP\HyperfScorm\Request\RequestUploadScormPackage;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackage;
use OnixSystemsPHP\HyperfScorm\Action\UploadScormPackageAction;
use OnixSystemsPHP\HyperfScorm\Action\GetScormPlayerAction;
use OnixSystemsPHP\HyperfScorm\Service\CreateScormPackageService;
use OnixSystemsPHP\HyperfScorm\Service\ScormPackageService;
use OnixSystemsPHP\HyperfScorm\Service\UploadScormPackageService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

class ScormController extends AbstractController
{
    /**
     * @throws \Exception
     */
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
    public function upload(
        RequestUploadScormPackage $request,
        UploadScormPackageService $uploadPackageService
    ): ResourceScormPackage {
        $package = $uploadPackageService->run(UploadPackageDTO::make($request->validated()));

        return ResourceScormPackage::make($package);
    }

    #[OA\Get(
        path: '/v1/scorm/packages/{id}/player',
        operationId: 'getScormPlayer',
        summary: 'Get SCORM player for package',
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 200, description: 'SCORM player data'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function getPlayer(
        RequestInterface $request,
        int $id,
        GetScormPlayerAction $playerAction
    ): ResponseInterface {
        $userId = (int) $request->getAttribute('user_id', 1); // Get from auth middleware

        try {
            $playerData = $playerAction->execute($id, $userId);

            return $this->response->json([
                'success' => true,
                'data' => $playerData
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->response->withStatus(404)->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        } catch (\RuntimeException $e) {
            return $this->response->withStatus(400)->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    #[OA\Get(
        path: '/v1/scorm/packages/{id}/manifest',
        operationId: 'getScormManifest',
        summary: 'Get SCORM manifest data for package',
        tags: ['scorm'],
        responses: [
            new OA\Response(response: 200, description: 'SCORM manifest data'),
            new OA\Response(ref: '#/components/responses/404', response: 404),
            new OA\Response(ref: '#/components/responses/401', response: 401),
            new OA\Response(ref: '#/components/responses/500', response: 500),
        ],
    )]
    public function getManifest(
        RequestInterface $request,
        int $id,
        GetScormPlayerAction $playerAction
    ): ResponseInterface {
        try {
            $manifestData = $playerAction->getManifestData($id);

            return $this->response->json([
                'success' => true,
                'data' => $manifestData->toArray()
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->response->withStatus(404)->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

//    #[OA\Post(
//        path: '/v1/scorm/packages',
//        operationId: 'createScormPackage',
//        summary: 'Create SCORM package manually',
//        tags: ['scorm'],
//        responses: [
//            new OA\Response(response: 201, description: 'SCORM package created successfully'),
//            new OA\Response(ref: '#/components/responses/400', response: 400),
//            new OA\Response(ref: '#/components/responses/401', response: 401),
//            new OA\Response(ref: '#/components/responses/500', response: 500),
//        ],
//    )]
//    public function create(RequestCreateScormPackage $request): ResourceScormPackage
//    {
//        $package = $this->createPackageService->run(
//            CreateScormPackageDTOFactory::make($request)
//        );
//
//        return ResourceScormPackage::make($package);
//    }
//
//    #[OA\Get(
//        path: '/v1/scorm/packages',
//        operationId: 'getScormPackages',
//        summary: 'Get list of SCORM packages',
//        tags: ['scorm'],
//        responses: [
//            new OA\Response(response: 200, description: 'List of SCORM packages'),
//            new OA\Response(ref: '#/components/responses/401', response: 401),
//            new OA\Response(ref: '#/components/responses/500', response: 500),
//        ],
//    )]
//    public function index(RequestInterface $request): ResponseInterface
//    {
//        $limit = (int) $request->query('limit', 50);
//        $offset = (int) $request->query('offset', 0);
//        $filters = $request->query('filters', []);
//        $search = $request->query('search');
//        $scormVersion = $request->query('scorm_version');
//        $activeOnly = filter_var($request->query('active_only', 'false'), FILTER_VALIDATE_BOOLEAN);
//
//        $packages = $this->scormPackageService->getAll(
//            $filters,
//            $limit,
//            $offset,
//            $search,
//            $scormVersion,
//            $activeOnly
//        );
//        $total = $this->scormPackageService->count($filters, $search, $scormVersion, $activeOnly);
//
//        return $this->response->json([
//            'success' => true,
//            'data' => ResourceScormPackage::collection($packages),
//            'meta' => [
//                'total' => $total,
//                'limit' => $limit,
//                'offset' => $offset,
//                'filters' => [
//                    'search' => $search,
//                    'scorm_version' => $scormVersion,
//                    'active_only' => $activeOnly
//                ]
//            ]
//        ]);
//    }
//
//    #[OA\Get(
//        path: '/v1/scorm/packages/{id}',
//        operationId: 'getScormPackage',
//        summary: 'Get SCORM package details',
//        tags: ['scorm'],
//        responses: [
//            new OA\Response(response: 200, description: 'SCORM package details'),
//            new OA\Response(ref: '#/components/responses/404', response: 404),
//            new OA\Response(ref: '#/components/responses/401', response: 401),
//            new OA\Response(ref: '#/components/responses/500', response: 500),
//        ],
//    )]
//    public function show(RequestInterface $request, int $id): ResponseInterface
//    {
//        $package = $this->scormPackageService->getById($id);
//
//        if (!$package) {
//            return $this->response->json([
//                'success' => false,
//                'message' => 'Package not found'
//            ], 404);
//        }
//
//        return $this->response->json([
//            'success' => true,
//            'data' => ResourceScormPackage::make($package)
//        ]);
//    }
//
//    #[OA\Delete(
//        path: '/v1/scorm/packages/{id}',
//        operationId: 'deleteScormPackage',
//        summary: 'Delete SCORM package',
//        tags: ['scorm'],
//        responses: [
//            new OA\Response(response: 204, description: 'SCORM package deleted successfully'),
//            new OA\Response(ref: '#/components/responses/404', response: 404),
//            new OA\Response(ref: '#/components/responses/401', response: 401),
//            new OA\Response(ref: '#/components/responses/500', response: 500),
//        ],
//    )]
//    public function delete(RequestInterface $request, int $id): ResponseInterface
//    {
//        $deleted = $this->scormPackageService->delete($id);
//
//        if (!$deleted) {
//            return $this->response->json([
//                'success' => false,
//                'message' => 'Package not found'
//            ], 404);
//        }
//
//        return $this->response->json([
//            'success' => true,
//            'message' => 'Package deleted successfully'
//        ], 204);
//    }
}
