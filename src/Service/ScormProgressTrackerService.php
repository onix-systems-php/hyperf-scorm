<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Sender;
use OnixSystemsPHP\HyperfCore\Service\Service;
use OnixSystemsPHP\HyperfScorm\WebSocket\ScormProgressWebSocketController;
use Psr\Log\LoggerInterface;

#[Service]
class ScormProgressTrackerService
{
    private const JOB_STATUS_PREFIX = 'scorm:job:';
    private const JOB_TTL = 3600; // 1 hour

    public function __construct(
        private readonly Redis $redis,
        private readonly Sender $sender,
        private readonly LoggerInterface $logger,
    ) {}

    public function updateProgress(string $jobId, string $stage, int $progress): void
    {
        $key = self::JOB_STATUS_PREFIX . $jobId;

        $data = [
            'job_id' => $jobId,
            'status' => 'processing',
            'stage' => $stage,
            'progress' => $progress,
            'updated_at' => time(),
        ];

        // Сохраняем в Redis
        $this->redis->setex($key, self::JOB_TTL, json_encode($data));

        // WebSocket broadcast всем подписанным клиентам
        $this->broadcastToWebSocket($jobId, $data);
    }

    public function markComplete(string $jobId, int $packageId): void
    {
        $key = self::JOB_STATUS_PREFIX . $jobId;

        $data = [
            'job_id' => $jobId,
            'status' => 'completed',
            'stage' => 'completed',
            'progress' => 100,
            'package_id' => $packageId,
            'completed_at' => time(),
        ];

        $this->redis->setex($key, self::JOB_TTL, json_encode($data));
        $this->broadcastToWebSocket($jobId, $data);
    }

    public function markFailed(string $jobId, string $error): void
    {
        $key = self::JOB_STATUS_PREFIX . $jobId;

        $data = [
            'job_id' => $jobId,
            'status' => 'failed',
            'stage' => 'failed',
            'progress' => 0,
            'error' => $error,
            'failed_at' => time(),
        ];

        $this->redis->setex($key, self::JOB_TTL, json_encode($data));
        $this->broadcastToWebSocket($jobId, $data);
    }

    public function getJobStatus(string $jobId): ?array
    {
        $key = self::JOB_STATUS_PREFIX . $jobId;
        $data = $this->redis->get($key);

        if ($data === null) {
            return null;
        }

        return json_decode($data, true);
    }

    private function broadcastToWebSocket(string $jobId, array $data): void
    {
        try {
            $fds = ScormProgressWebSocketController::getSubscribedFds($jobId);

            if (empty($fds)) {
                return;
            }

            $message = json_encode([
                'type' => 'progress',
                'data' => $data,
            ]);

            foreach ($fds as $fd) {
                $this->sender->push($fd, $message);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('WebSocket broadcast failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
