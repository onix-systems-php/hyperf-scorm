<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Contract\Gateway;

interface ScormProgressGatewayInterface
{
    public function statusJob(string $jobId): array;

    public function cancelJob(string $jobId): array;
}
