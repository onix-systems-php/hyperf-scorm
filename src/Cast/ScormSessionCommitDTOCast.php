<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Cast;

use Hyperf\Contract\CastsAttributes;
use OnixSystemsPHP\HyperfScorm\DTO\CmiDataDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormSessionCommitDTO;
use OnixSystemsPHP\HyperfScorm\Enum\ScormVersionEnum;

/**
 * Cast for ScormVersionEnum to handle database serialization/deserialization
 */
class ScormSessionCommitDTOCast extends DtoCast
{
    protected function dtoClass(): string
    {
        return ScormSessionCommitDTO::class;
    }
}

