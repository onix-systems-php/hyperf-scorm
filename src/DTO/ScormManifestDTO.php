<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\DTO;

use ClassTransformer\Attributes\ConvertArray;
use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for parsed SCORM manifest data.
 */
class ScormManifestDTO extends AbstractDTO
{
    public string $title;

    public string $version;

    #[ConvertArray(ScoDTO::class)]
    public array $scos;

    public ?string $description;

    public function getPrimarySco(): ?ScoDTO
    {
        return $this->scos[0] ?? null;
    }

    public function getPrimaryLauncherPath(): ?string
    {
        $primarySco = $this->getPrimarySco();
        return $primarySco?->launcher_path;
    }

    public function getScoByIdentifier(string $identifier): ?ScoDTO
    {
        foreach ($this->scos as $sco) {
            if ($sco->identifier === $identifier) {
                return $sco;
            }
        }

        return null;
    }

    public function hasScos(): bool
    {
        return ! empty($this->scos);
    }

    public function getScoCount(): int
    {
        return count($this->scos);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'version' => $this->version,
            'version_label' => $this->version,
            'description' => $this->description,
            'scos' => array_map(fn ($sco) => $sco->toArray(), $this->scos),
            'primary_launch_path' => $this->getPrimaryLauncherPath(),
            'sco_count' => $this->getScoCount(),
        ];
    }
}
