<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Contract\Gateway;

use OnixSystemsPHP\HyperfScorm\DTO\ScormPlayerDTO;

interface ScormPlayerGatewayInterface
{
    public function launch(int $packageId, int $userId): ScormPlayerDTO;
}
