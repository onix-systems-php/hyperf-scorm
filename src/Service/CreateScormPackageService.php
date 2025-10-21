<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\CreateScormPackageDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;

/**
 * Service for creating SCORM packages
 */
#[Service]
class CreateScormPackageService
{
    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository
    ) {
    }

    #[Transactional(attempts: 1)]
    public function run(CreateScormPackageDTO $dto): ScormPackage
    {
        $this->validatePackage($dto);

        $package = $this->scormPackageRepository->create([
            'title' => $dto->title,
            'version' => $dto->version ?? '1.0',
            'identifier' => $dto->identifier,
            'content_path' => $dto->contentPath,
            'manifest_data' => $dto->manifestData ?? [],
            'scorm_version' => $dto->scormVersion ?? ScormVersionEnum::SCORM_12->value,
        ]);

        return $this->scormPackageRepository->save($package);
    }

    private function validatePackage(CreateScormPackageDTO $dto): void
    {
        if (empty($dto->title)) {
            throw new \InvalidArgumentException('Package title is required');
        }

        if (empty($dto->identifier)) {
            throw new \InvalidArgumentException('Package identifier is required');
        }

        if (!in_array($dto->scormVersion, ScormVersionEnum::values())) {
            throw new \InvalidArgumentException('Invalid SCORM version');
        }
    }
}
