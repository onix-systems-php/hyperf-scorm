<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Cast;

use OnixSystemsPHP\HyperfScorm\DTO\CmiDataDTO;

/**
 * Cast for CMI data between JSON and CmiDataDTO
 */
class CmiDataCast extends DtoCast
{
    protected function dtoClass(): string
    {
        return CmiDataDTO::class;
    }
}
