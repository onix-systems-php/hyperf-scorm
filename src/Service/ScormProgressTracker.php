<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\Contract\ProgressTrackerInterface;
use OnixSystemsPHP\HyperfScorm\DTO\ProgressContext;
use Psr\Log\LoggerInterface;
use Throwable;

#[Service]
class ScormProgressTracker implements ProgressTrackerInterface
{
    public function __construct(
        private readonly ScormJobStatusService $jobStatusService,
        private readonly ScormWebSocketNotificationService $webSocketService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function track(ProgressContext $context, array $progressData): void
    {
        $this->updateRedisProgress($context, $progressData);

        $this->sendWebSocketNotification($context, $progressData);
    }

    private function updateRedisProgress(ProgressContext $context, array $progressData): void
    {
        try {
            $this->jobStatusService->updateProgress($context->jobId, $progressData);

            $this->logger->debug('Progress updated in Redis', [
                'job_id' => $context->jobId,
                'progress' => $progressData['progress'] ?? null,
                'stage' => $progressData['stage'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Failed to update Redis progress', [
                'job_id' => $context->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function sendWebSocketNotification(ProgressContext $context, array $progressData): void
    {
        try {
            $this->webSocketService->sendUploadProgressUpdate(
                $context->userId,
                $context->jobId,
                $progressData
            );

            $this->logger->debug('WebSocket notification sent', [
                'job_id' => $context->jobId,
                'user_id' => $context->userId,
                'stage' => $progressData['stage'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to send WebSocket notification', [
                'job_id' => $context->jobId,
                'user_id' => $context->userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
