<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

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
