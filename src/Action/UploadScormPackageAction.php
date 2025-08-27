<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Action;

use OnixSystemsPHP\HyperfScorm\DTO\UploadPackageDTO;
use OnixSystemsPHP\HyperfScorm\Service\ScormFileProcessor;
use OnixSystemsPHP\HyperfScorm\Service\ScormValidator;
use OnixSystemsPHP\HyperfScorm\Service\UploadScormPackageService;
use OnixSystemsPHP\HyperfScorm\Model\ScormPackage;

class UploadScormPackageAction
{
    public function __construct(
        private readonly UploadScormPackageService $uploadService,
        private readonly ScormFileProcessor $fileProcessor,
        private readonly ScormValidator $validator
    ) {
    }

    public function execute(UploadPackageDTO $dto): ScormPackage
    {
        // Валидация файла
        $this->validator->validateUploadedFile($dto->file);
        
        // Обработка файла
        $processedData = $this->fileProcessor->processUploadedFile($dto->file);
        
        // Загрузка пакета
        return $this->uploadService->run($dto);
    }
}


