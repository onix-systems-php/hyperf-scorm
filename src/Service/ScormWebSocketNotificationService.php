<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;

class ScormWebSocketNotificationService
{
    private const USER_CHANNEL_PREFIX = 'scorm_user:';

    private readonly array $stageMessages;

    public function __construct(
        private readonly Redis $redis,
        private readonly Sender $sender,
        private readonly LoggerInterface $logger,
        private readonly ConfigInterface $config,
        private readonly WebSocketConnectionService $connectionRegistry,
    ) {
        $this->stageMessages = $this->config->get('scorm.messages.stage_details', [
            'initializing' => 'Preparing SCORM package...',
            'extracting' => 'Extracting files from package...',
            'processing' => 'Processing SCORM manifest...',
            'uploading' => 'Uploading content to storage...',
            'completed' => 'Package processing completed',
            'failed' => 'Processing failed',
        ]);
    }

    public function subscribeToUpdates(int $userId, int $fd): void
    {
        $channel = self::USER_CHANNEL_PREFIX . $userId;

        try {
            // Store user connection mapping
            $this->redis->sadd("ws_connections:{$userId}", (string)$fd);
            $this->redis->expire("ws_connections:{$userId}", config('scorm.redis.ttl.websocket', 86400));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to subscribe user to SCORM notifications', [
                'user_id' => $userId,
                'fd' => $fd,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function unsubscribeFromUpdates(int $userId, int $fd): void
    {
        try {
            $this->redis->srem("ws_connections:{$userId}", (string)$fd);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to unsubscribe user from SCORM notifications', [
                'user_id' => $userId,
                'fd' => $fd,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendUploadProgressUpdate(int $userId, string $jobId, array $progressData): void
    {
        try {
            $connections = $this->connectionRegistry->getSubscribedFds($jobId);

            if (empty($connections)) {
                $this->logger->debug('No WebSocket connections for job', [
                    'job_id' => $jobId,
                    'user_id' => $userId,
                ]);
                return;
            }

            $message = json_encode([
                'type' => 'progress',
                'data' => [
                    'job_id' => $jobId,
                    'status' => $progressData['status'] ?? 'processing',
                    'stage' => $progressData['stage'] ?? 'processing',
                    'progress' => (int)($progressData['progress'] ?? 0),
                    'package_id' => $progressData['package_id'] ?? null,
                    'error' => $progressData['error'] ?? null,
                ],
            ]);

            $sentCount = 0;
            foreach ($connections as $fd) {
                try {
                    $this->sender->push($fd, $message);
                    ++$sentCount;
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to push to WebSocket connection', [
                        'fd' => $fd,
                        'job_id' => $jobId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('Sent SCORM progress update via WebSocket', [
                'job_id' => $jobId,
                'user_id' => $userId,
                'connections' => count($connections),
                'sent' => $sentCount,
                'progress' => $progressData['progress'] ?? 0,
                'stage' => $progressData['stage'] ?? 'unknown',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send SCORM upload progress update via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendCompletionNotification(int $userId, string $jobId, array $result): void
    {
        try {
            $connections = $this->connectionRegistry->getSubscribedFds($jobId);

            if (empty($connections)) {
                return;
            }

            $message = json_encode([
                'type' => 'progress',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'completed',
                    'stage' => 'completed',
                    'progress' => 100,
                    'package_id' => $result['package_id'] ?? null,
                    'error' => null,
                ],
            ]);

            $sentCount = 0;
            foreach ($connections as $fd) {
                try {
                    $this->sender->push($fd, $message);
                    ++$sentCount;
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to push completion to WebSocket', [
                        'fd' => $fd,
                        'job_id' => $jobId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('Sent SCORM completion notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'package_id' => $result['package_id'] ?? null,
                'connections' => count($connections),
                'sent' => $sentCount,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send SCORM completion notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendErrorNotification(int $userId, string $jobId, string $error): void
    {
        try {
            $connections = $this->connectionRegistry->getSubscribedFds($jobId);

            if (empty($connections)) {
                return; // No active connections
            }

            $message = json_encode([
                'type' => 'progress',
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'failed',
                    'stage' => 'failed',
                    'progress' => 0,
                    'package_id' => null,
                    'error' => $error,
                ],
            ]);

            $sentCount = 0;
            foreach ($connections as $fd) {
                try {
                    $this->sender->push($fd, $message);
                    ++$sentCount;
                } catch (\Throwable $e) {
                    $this->logger->warning('Failed to push error to WebSocket', [
                        'fd' => $fd,
                        'job_id' => $jobId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->warning('Sent SCORM error notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $error,
                'connections' => count($connections),
                'sent' => $sentCount,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send SCORM error notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getUserConnectionsCount(int $userId): int
    {
        try {
            return $this->redis->scard("ws_connections:{$userId}");
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get user WebSocket connections count', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    public function cleanupDeadConnections(): int
    {
        try {
            $pattern = 'ws_connections:*';
            $keys = $this->redis->keys($pattern);
            $cleanedCount = 0;

            foreach ($keys as $key) {
                $connections = $this->redis->smembers($key);

                foreach ($connections as $fd) {
                    try {
                        // Try to send a ping to test connection
                        $this->sender->push((int)$fd, json_encode(['type' => 'ping']));
                    } catch (\Throwable $e) {
                        // Connection is dead, remove it
                        $this->redis->srem($key, $fd);
                        ++$cleanedCount;
                    }
                }

                // Remove empty keys
                if ($this->redis->scard($key) === 0) {
                    $this->redis->del($key);
                }
            }

            if ($cleanedCount > 0) {
                $this->logger->info('Cleaned up dead WebSocket connections', [
                    'cleaned_count' => $cleanedCount,
                ]);
            }

            return $cleanedCount;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cleanup dead WebSocket connections', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    private function calculateProcessedBytes(array $progressData): ?int
    {
        $fileSize = $progressData['file_size'] ?? null;
        $progress = $progressData['progress'] ?? 0;

        if ($fileSize === null || $progress <= 0) {
            return null;
        }

        return (int)($fileSize * $progress / 100);
    }

    private function getDefaultStageDetails(string $stage): string
    {
        return $this->stageMessages[$stage] ?? 'Processing...';
    }
}
