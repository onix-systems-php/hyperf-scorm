<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller;

use Hyperf\Filesystem\FilesystemFactory;
use Hyperf\HttpMessage\Stream\SwooleStream;
use OnixSystemsPHP\HyperfCore\Controller\AbstractController;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use OnixSystemsPHP\HyperfScorm\Service\MemeTypeResolverService;
use Psr\Http\Message\ResponseInterface;

class ScormProxyController extends AbstractController
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private readonly FilesystemFactory $filesystemFactory,
        private readonly MemeTypeResolverService $memeTypeResolverService,
    ) {
    }
    public function proxy(int $packageId, string $path): ResponseInterface
    {
        $package = $this->scormPackageRepository->getById($packageId, false, true);
        $fullPath = $this->buildFullPath($package, $path);
        $filesystem = $this->filesystemFactory->get($package->storage);

        try {
            $stream = $filesystem->readStream($fullPath);

            if ($stream === false) {
                throw new \RuntimeException("Failed to read SCORM file: {$path}", 500);
            }

            return $this->response
                ->withHeader('Content-Type', $this->memeTypeResolverService->getMimeTypeByPath($fullPath))
                ->withHeader('Cache-Control', 'public, max-age=1200, immutable')
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withBody(new SwooleStream(stream_get_contents($stream)));
        } finally {
            is_resource($stream) && fclose($stream);
        }
    }

    private function buildFullPath(ScormPackage $package, string $path): string
    {
        return ltrim(join(DIRECTORY_SEPARATOR, [
            ltrim($package->content_path, '/'),
            ltrim(urldecode($path), '/'),
        ]), '/');
    }
}
