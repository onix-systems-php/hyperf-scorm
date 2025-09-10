<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Request\RequestUploadScormPackage;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackage;
use OnixSystemsPHP\HyperfScorm\Service\UploadScormPackageService;
use OpenApi\Attributes as OA;

class ScormController extends AbstractController
{
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
