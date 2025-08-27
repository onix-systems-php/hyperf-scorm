<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\DTO;

use OnixSystemsPHP\HyperfCore\DTO\AbstractDTO;

/**
 * DTO for starting SCORM attempt
 */
class StartScormAttemptDTO extends AbstractDTO
{
    public function __construct(
        public int $packageId,
        public int $userId,
    ) {}
}
