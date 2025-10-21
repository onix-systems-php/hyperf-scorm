<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\DbConnection\Annotation\Transactional;
use OnixSystemsPHP\HyperfActionsLog\Event\Action;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepository;
use Psr\EventDispatcher\EventDispatcherInterface;

#[Service]
class DeleteScormPackageService
{
    public const ACTION = 'delete_scorm_package';

    public function __construct(
        private readonly ScormPackageRepository $scormPackageRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Transactional(attempts: 1)]
    public function run(int $packageId): void
    {
        $package = $this->scormPackageRepository->findById($packageId, true, true);
        $this->scormPackageRepository->delete($package);
        $this->eventDispatcher->dispatch(new Action(self::ACTION, $package, []));
    }
}
