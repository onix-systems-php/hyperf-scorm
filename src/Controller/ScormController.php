<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\HttpServer\Contract\RequestInterface;
use OnixSystemsPHP\HyperfCore\Resource\ResourceSuccess;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationRequestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Request\RequestUploadScormPackage;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackage;
use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormPackagePaginated;
use OnixSystemsPHP\HyperfScorm\Service\DeleteScormPackageService;
use OnixSystemsPHP\HyperfScorm\Service\UploadScormPackageService;
use OpenApi\Attributes as OA;
use function Hyperf\Support\make;

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
        UploadScormPackageService $service
    ): ResourceScormPackage {
        $package = $service->run(UploadPackageDTO::make($request->validated()));

        return ResourceScormPackage::make($package);
    }

    #[OA\Get(//@SONAR_STOP@
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
    public function index(RequestInterface $request): ResourceScormPackagePaginated
    {
        /**@var ScormPackageRepository $scormPackageRepository**/
        $scormPackageRepository = make(ScormPackageRepository::class);

        $packages = $scormPackageRepository->getPaginated(
            $request->getQueryParams(),
            PaginationRequestDTO::make($request)
        );

        return ResourceScormPackagePaginated::make($packages);
    }
    #[OA\Delete(
        path: '/v1/scorm/packages/{id}',
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
    public function destroy(DeleteScormPackageService $service, int $id): ResourceSuccess
    {
        $service->run($id);
        return ResourceSuccess::make([]);
    }
}
