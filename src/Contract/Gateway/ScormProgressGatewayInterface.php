<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Contract\Gateway;

use OnixSystemsPHP\HyperfScorm\Resource\ResourceScormJobStatus;

interface ScormProgressGatewayInterface
{
    public function statusJob(string $jobId): ResourceScormJobStatus;

    public function cancelJob(string $jobId): array;
}
