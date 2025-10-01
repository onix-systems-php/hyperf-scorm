<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Repository\ScormScoRepository;
use OnixSystemsPHP\HyperfScorm\Model\ScormSco;

#[Service]
class ScormScoService
{
    public function __construct(
        private ScormScoRepository $scoRepository
    ) {

    }

    public function getById(int $id): ?ScormSco
    {
        return $this->scoRepository->findById($id);
    }

    public function getByIdentifier(string $identifier, int $packageId): ?ScormSco
    {
        return $this->scoRepository->findByIdentifier($identifier, $packageId);
    }

    public function getByPackageId(int $packageId): array
    {
        return $this->scoRepository->findByPackageId($packageId);
    }

    public function create(array $data): ScormSco
    {
        return $this->scoRepository->create($data);
    }

    public function createFromManifest(int $packageId, array $scoDataArray): array
    {
        $scos = [];

        foreach ($scoDataArray as $scoData) {
            $scoRecord = [
                'package_id' => $packageId,
                'identifier' => $scoData['identifier'],
                'title' => $scoData['title'],
                'launch_url' => $scoData['launch_url'],
                'prerequisites' => $scoData['prerequisites'] ? [$scoData['prerequisites']] : null,
                'parameters' => $scoData['parameters'] ? [$scoData['parameters']] : null,
                'mastery_score' => $scoData['mastery_score'],
                'max_time_allowed' => $scoData['max_time_allowed'],
                'time_limit_action' => $scoData['time_limit_action'],
            ];

            $scos[] = $this->scoRepository->create($scoRecord);
        }

        return $scos;
    }

    public function update(int $id, array $data): ?ScormSco
    {
        $sco = $this->scoRepository->findById($id);
        if (!$sco) {
            return null;
        }

        $sco->fill(array_filter($data));
        return $this->scoRepository->save($sco);
    }

    public function delete(int $id): bool
    {
        return $this->scoRepository->delete($id);
    }
}
