<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Cast;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormManifestDTO;

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
