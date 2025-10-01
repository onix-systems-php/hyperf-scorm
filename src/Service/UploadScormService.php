<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfScorm\DTO\UploadScormDTO;
use OnixSystemsPHP\HyperfScorm\Exception\ScormUploadException;
use Psr\Log\LoggerInterface;
use Throwable;

class UploadScormService
{
    public const ACTION = 'upload_scorm';

    public function __construct(
        private readonly AsyncScormProcessingService $asyncProcessingService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Process single SCORM upload
     */
    public function run(UploadScormDTO $uploadDTO): array
    {
        $this->validate($uploadDTO);

        try {
            $jobId = $this->asyncProcessingService->queueProcessing(
                $uploadDTO->scormFile,
                $uploadDTO->userId,
                $uploadDTO->metadata
            );

            $this->logger->info('SCORM upload queued successfully', [
                'action' => self::ACTION,
                'job_id' => $jobId,
                'user_id' => $uploadDTO->userId,
                'filename' => $uploadDTO->scormFile->getClientFilename(),
                'file_size' => $uploadDTO->scormFile->getSize(),
            ]);

            return [
                'job_id' => $jobId,
                'message' => 'SCORM package queued for processing',
                'file_size' => $uploadDTO->scormFile->getSize(),
                'original_filename' => $uploadDTO->scormFile->getClientFilename(),
            ];

        } catch (Throwable $e) {
            $this->logger->error('Failed to upload SCORM package', [
                'action' => self::ACTION,
                'user_id' => $uploadDTO->userId,
                'filename' => $uploadDTO->scormFile->getClientFilename(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw ScormUploadException::processingFailed(
                $e->getMessage(),
                [
                    'user_id' => $uploadDTO->userId,
                    'filename' => $uploadDTO->scormFile->getClientFilename(),
                    'file_size' => $uploadDTO->scormFile->getSize(),
                ]
            );
        }
    }

    /**
     * Validate upload data
     */
    private function validate(UploadScormDTO $uploadDTO): void
    {
        // File size validation (additional to Request validation)
        $maxSize = 600 * 1024 * 1024; // 600MB
        if ($uploadDTO->scormFile->getSize() > $maxSize) {
            throw ScormUploadException::fileSizeExceeded(
                $uploadDTO->scormFile->getSize(),
                $maxSize,
                ['filename' => $uploadDTO->scormFile->getClientFilename()]
            );
        }

        // File extension validation
        $filename = $uploadDTO->scormFile->getClientFilename();
        if (!str_ends_with(strtolower($filename), '.zip')) {
            throw ScormUploadException::invalidFileType(
                'non-zip file',
                ['zip'],
                ['filename' => $filename]
            );
        }

        // MIME type validation
        $mimeType = $uploadDTO->scormFile->getClientMediaType();
        if ($mimeType !== 'application/zip' && $mimeType !== 'application/x-zip-compressed') {
            throw ScormUploadException::invalidFileType(
                $mimeType ?? 'unknown',
                ['application/zip', 'application/x-zip-compressed'],
                ['filename' => $filename]
            );
        }

        // Metadata validation
        if (!empty($uploadDTO->metadata)) {
            $errors = [];
            foreach ($uploadDTO->metadata as $key => $value) {
                if (!is_string($value) || strlen($value) > 255) {
                    $errors[] = "Invalid metadata value for '{$key}'";
                }
            }

            if (!empty($errors)) {
                throw ScormUploadException::validationFailed($errors, [
                    'filename' => $filename,
                ]);
            }
        }
    }
}
