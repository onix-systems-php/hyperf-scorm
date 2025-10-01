<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;
use OnixSystemsPHP\HyperfScorm\Job\ProcessScormPackageJob;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * WebSocket notification service for SCORM file upload progress
 * Sends real-time updates during file upload process
 */
class ScormWebSocketNotificationService
{
    private const CHANNEL_PREFIX = 'scorm_notifications:';
    private const USER_CHANNEL_PREFIX = 'scorm_user:';

    public function __construct(
        private readonly Redis $redis,
        private readonly Sender $sender,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Subscribe to SCORM processing updates
     */
    public function subscribeToUpdates(int $userId, int $fd): void
    {
        $channel = self::USER_CHANNEL_PREFIX . $userId;
        
        try {
            // Store user connection mapping
            $this->redis->sadd("ws_connections:{$userId}", (string)$fd);
            $this->redis->expire("ws_connections:{$userId}", 86400); // 24 hours

        } catch (Throwable $e) {
            $this->logger->error('Failed to subscribe user to SCORM notifications', [
                'user_id' => $userId,
                'fd' => $fd,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Unsubscribe from SCORM processing updates
     */
    public function unsubscribeFromUpdates(int $userId, int $fd): void
    {
        try {
            $this->redis->srem("ws_connections:{$userId}", (string)$fd);

        } catch (Throwable $e) {
            $this->logger->error('Failed to unsubscribe user from SCORM notifications', [
                'user_id' => $userId,
                'fd' => $fd,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send progress update to user's WebSocket connections
     * Sends notifications for all processing stages
     */
    public function sendUploadProgressUpdate(int $userId, string $jobId, array $progressData): void
    {
        // Send notifications for all processing stages
        $allStages = ['initializing', 'extracting', 'processing', 'uploading', 'completed', 'failed'];
        $currentStage = $progressData['stage'] ?? 'unknown';
        
        if (!in_array($currentStage, $allStages)) {
            return; // Skip unknown stages
        }

        try {
            $connections = $this->redis->smembers("ws_connections:{$userId}");
            
            if (empty($connections)) {
                return; // No active connections
            }

            $message = json_encode([
                'type' => 'scorm_upload_progress',
                'job_id' => $jobId,
                'user_id' => $userId,
                'progress' => [
                    'status' => $progressData['status'] ?? 'unknown',
                    'stage' => $currentStage,
                    'progress' => (int)($progressData['progress'] ?? 0),
                    'stage_details' => $progressData['stage_details'] ?? $this->getDefaultStageDetails($currentStage),
                    'file_size' => $progressData['file_size'] ?? null,
                    'processed_bytes' => $progressData['processed_bytes'] ?? $this->calculateProcessedBytes($progressData),
                    'memory_usage' => $progressData['memory_usage'] ?? null,
                    'eta_seconds' => $progressData['eta_seconds'] ?? null,
                    'speed_mbps' => $progressData['speed_mbps'] ?? null,
                ],
                'timestamp' => time(),
            ]);

            $activeConnections = [];
            foreach ($connections as $fd) {
                try {
                    $this->sender->push((int)$fd, $message);
                    $activeConnections[] = $fd;
                } catch (Throwable $e) {
                    // Connection is dead, remove it
                    $this->redis->srem("ws_connections:{$userId}", $fd);
                }
            }

        } catch (Throwable $e) {
            $this->logger->error('Failed to send SCORM upload progress update via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Send completion notification
     */
    public function sendCompletionNotification(int $userId, string $jobId, array $result): void
    {
        try {
            $connections = $this->redis->smembers("ws_connections:{$userId}");
            
            if (empty($connections)) {
                return; // No active connections
            }

            $message = json_encode([
                'type' => 'scorm_completed',
                'job_id' => $jobId,
                'user_id' => $userId,
                'result' => $result,
                'timestamp' => time(),
                'success' => ($result['status'] ?? '') === 'completed',
            ]);

            foreach ($connections as $fd) {
                try {
                    $this->sender->push((int)$fd, $message);
                } catch (Throwable $e) {
                    // Connection is dead, remove it
                    $this->redis->srem("ws_connections:{$userId}", $fd);
                }
            }

            $this->logger->info('Sent SCORM completion notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'success' => ($result['status'] ?? '') === 'completed',
                'connections_count' => count($connections),
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Failed to send SCORM completion notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send error notification
     */
    public function sendErrorNotification(int $userId, string $jobId, string $error): void
    {
        try {
            $connections = $this->redis->smembers("ws_connections:{$userId}");
            
            if (empty($connections)) {
                return; // No active connections
            }

            $message = json_encode([
                'type' => 'scorm_error',
                'job_id' => $jobId,
                'user_id' => $userId,
                'error' => $error,
                'timestamp' => time(),
            ]);

            foreach ($connections as $fd) {
                try {
                    $this->sender->push((int)$fd, $message);
                } catch (Throwable $e) {
                    // Connection is dead, remove it
                    $this->redis->srem("ws_connections:{$userId}", $fd);
                }
            }

            $this->logger->warning('Sent SCORM error notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $error,
                'connections_count' => count($connections),
            ]);

        } catch (Throwable $e) {
            $this->logger->error('Failed to send SCORM error notification via WebSocket', [
                'user_id' => $userId,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user's active WebSocket connections count
     */
    public function getUserConnectionsCount(int $userId): int
    {
        try {
            return $this->redis->scard("ws_connections:{$userId}");
        } catch (Throwable $e) {
            $this->logger->error('Failed to get user WebSocket connections count', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Clean up dead connections for all users
     */
    public function cleanupDeadConnections(): int
    {
        try {
            $pattern = "ws_connections:*";
            $keys = $this->redis->keys($pattern);
            $cleanedCount = 0;

            foreach ($keys as $key) {
                $connections = $this->redis->smembers($key);
                
                foreach ($connections as $fd) {
                    try {
                        // Try to send a ping to test connection
                        $this->sender->push((int)$fd, json_encode(['type' => 'ping']));
                    } catch (Throwable $e) {
                        // Connection is dead, remove it
                        $this->redis->srem($key, $fd);
                        $cleanedCount++;
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

        } catch (Throwable $e) {
            $this->logger->error('Failed to cleanup dead WebSocket connections', [
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Calculate processed bytes based on progress data
     */
    private function calculateProcessedBytes(array $progressData): ?int
    {
        $fileSize = $progressData['file_size'] ?? null;
        $progress = $progressData['progress'] ?? 0;
        
        if ($fileSize === null || $progress <= 0) {
            return null;
        }

        // Calculate processed bytes based on progress percentage
        return (int)($fileSize * ($progress / 100));
    }

    /**
     * Get default stage details for better UX
     */
    private function getDefaultStageDetails(string $stage): string
    {
        return match ($stage) {
            'initializing' => 'Preparing SCORM package...',
            'extracting' => 'Extracting files from package...',
            'processing' => 'Processing SCORM manifest...',
            'uploading' => 'Uploading content to storage...',
            'completed' => 'Package processing completed',
            'failed' => 'Processing failed',
            default => 'Processing...',
        };
    }
}