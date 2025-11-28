<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use Hyperf\Filesystem\FilesystemFactory;


class ScormProxyService
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
       private readonly FilesystemFactory $filesystemFactory,
    ){
    }

    /**
     * @return array{0: resource, 1: string, 2: ScormPackage}
     */
    public function run($packageId, $path): array
    {
        $package = $this->scormPackageRepository->getById($packageId, false, true);
        $fullPath = $this->buildFullPath($package, $path);
        $filesystem = $this->filesystemFactory->get($package->storage);
        $stream =  $filesystem->readStream($fullPath);

        if ($stream === false) {
            throw new \RuntimeException("Failed to read SCORM file: {$path}", 500);
        }

        return  [$package, $fullPath, $stream];
    }


    private function buildFullPath(ScormPackage $package, string $path): string
    {
        return ltrim(join(DIRECTORY_SEPARATOR, [
            ltrim($package->content_path, '/'),
            ltrim(urldecode($path), '/'),
        ]), '/');
    }
}
