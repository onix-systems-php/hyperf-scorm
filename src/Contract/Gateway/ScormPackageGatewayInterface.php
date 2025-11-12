<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Contract\Gateway;

use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationRequestDTO;
use OnixSystemsPHP\HyperfCore\DTO\Common\PaginationResultDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormAsyncJobDTO;
use OnixSystemsPHP\HyperfScorm\DTO\ScormUploadDTO;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;

interface ScormPackageGatewayInterface
{
    public function index(array $filters, PaginationRequestDTO $paginationRequestDTO): PaginationResultDTO;
    public function upload(ScormUploadDTO $scormUploadDTO, int $userId): ScormAsyncJobDTO;
    public function destroy(int $packageId): ScormPackage;
}
