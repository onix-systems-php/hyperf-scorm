<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpMessage\Stream\SwooleStream;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Service\MemeTypeResolverService;
use OnixSystemsPHP\HyperfScorm\Service\ScormProxyService;
use Psr\Http\Message\ResponseInterface;

class ScormProxyController extends AbstractController
{
    public function __construct(
        private readonly ScormProxyService $scormProxyService,
        private readonly MemeTypeResolverService $memeTypeResolverService,
    ) {
    }
    public function proxy(int $packageId, string $path): ResponseInterface
    {
        [$package, $fullPath, $stream] = $this->scormProxyService->run($packageId, $path);

        try {
            $etag = sprintf('"%d-%s"',
                $packageId,
                $package->updated_at->timestamp
            );

            return $this->response
                ->withHeader('Content-Type', $this->memeTypeResolverService->getMimeTypeByPath($fullPath))
                ->withHeader('Cache-Control', 'public, max-age=1200, immutable')
                ->withHeader('ETag', $etag)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withBody(new SwooleStream(stream_get_contents($stream)));
        } finally {
            is_resource($stream) && fclose($stream);
        }
    }

}
