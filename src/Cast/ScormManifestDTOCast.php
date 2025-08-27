<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Cast;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScoDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

class ScormManifestDTOCast extends DtoCast
{
    /**
     * @return class-string<AbstractDTO>
     */
    protected function dtoClass(): string
    {
        return ScormManifestDTO::class;
    }
}
