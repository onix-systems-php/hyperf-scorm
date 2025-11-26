<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfActionsLog\Event\Action;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\DTO\CreateScormPackageDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Service]
class CreateScormPackageService
{
    public const ACTION = 'create_scorm_package';

    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private EventDispatcherInterface $eventDispatcher,
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

        $this->eventDispatcher->dispatch(new Action(self::ACTION, $package, $package->toArray()));

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

        if (! in_array($dto->version, ScormVersionEnum::values())) {
            throw new \InvalidArgumentException('Invalid SCORM version');
        }
    }
}
