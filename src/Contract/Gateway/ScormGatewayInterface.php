<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Contract\Gateway;

interface ScormGatewayInterface extends
    ScormPlayerGatewayInterface,
    ScormProgressGatewayInterface,
    ScormPackageGatewayInterface
{
}
