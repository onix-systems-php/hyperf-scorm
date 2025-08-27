<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\Repository\ScormPackageRepositoryInterface;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;
use Hyperf\Collection\Collection;

class ScormPackageService
{
    public function __construct(
        private ScormPackageRepositoryInterface $packageRepository
    ) {}

    public function getById(int $id): ?ScormPackage
    {
        return $this->packageRepository->findById($id);
    }

    public function getByIdentifier(string $identifier): ?ScormPackage
    {
        return $this->packageRepository->findByIdentifier($identifier);
    }

    public function getAll(
        array $filters = [],
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $scormVersion = null,
        bool $activeOnly = false
    ): Collection {
        return $this->packageRepository->findAllWithFilters(
            $filters,
            $limit,
            $offset,
            $search,
            $scormVersion,
            $activeOnly
        );
    }

    public function create(array $data): ScormPackage
    {
        $package = new ScormPackage();
        $package->fill([
            'title' => $data['title'],
            'version' => $data['version'] ?? '1.0',
            'identifier' => $data['identifier'],
            'manifest_path' => $data['manifest_path'],
            'content_path' => $data['content_path'],
            'manifest_data' => $data['manifest_data'] ?? [],
            'scorm_version' => $data['scorm_version'] ?? '1.2',
        ]);

        return $this->packageRepository->save($package);
    }

    public function update(int $id, array $data): ?ScormPackage
    {
        $package = $this->packageRepository->findById($id);
        if (!$package) {
            return null;
        }

        $package->fill(array_filter($data));

        return $this->packageRepository->save($package);
    }

    public function delete(int $id): bool
    {
        return $this->packageRepository->delete($id);
    }

    public function count(
        array $filters = [],
        ?string $search = null,
        ?string $scormVersion = null,
        bool $activeOnly = false
    ): int {
        return $this->packageRepository->countWithFilters($filters, $search, $scormVersion, $activeOnly);
    }
}
