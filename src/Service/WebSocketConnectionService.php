<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Service;

use Hyperf\Redis\Redis;
use OnixSystemsPHP\HyperfCore\Service\Service;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;

#[Service]
class WebSocketConnectionService
{
    private const REDIS_KEY_PREFIX = 'ws_job_connections:';
    private const REDIS_FD_TO_JOB_PREFIX = 'ws_fd_to_job:';

    public function __construct(
        private readonly Redis $redis,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function registerConnection(string $jobId, int $fd): void
    {
        $this->redis->hSet(self::REDIS_KEY_PREFIX . $jobId, (string)$fd, time());
        $this->redis->set(
            self::REDIS_FD_TO_JOB_PREFIX . $fd,
            $jobId,
            ['EX' => config('scorm.redis.ttl.websocket', 86400)]
        );

        $this->logger->debug('[WS Registry] Connection registered', [
            'job_id' => $jobId,
            'fd' => $fd,
        ]);
    }

    public function unregisterConnection(string $jobId, int $fd): void
    {
        $this->redis->hDel(self::REDIS_KEY_PREFIX . $jobId, (string)$fd);
        $this->redis->del(self::REDIS_FD_TO_JOB_PREFIX . $fd);

        $remaining = $this->redis->hLen(self::REDIS_KEY_PREFIX . $jobId);
        if ($remaining === 0) {
            $this->redis->del(self::REDIS_KEY_PREFIX . $jobId);
        }

        $this->logger->debug('[WS Registry] Connection unregistered', [
            'job_id' => $jobId,
            'fd' => $fd,
            'remaining_connections' => $remaining,
        ]);
    }

    public function getSubscribedFds(string $jobId): array
    {
        $connections = $this->redis->hGetAll(self::REDIS_KEY_PREFIX . $jobId);

        $this->logger->info('[WS Registry] Getting subscribed FDs from Redis', [
            'job_id' => $jobId,
            'redis_key' => self::REDIS_KEY_PREFIX . $jobId,
            'total_connections' => count($connections),
        ]);

        $fds = array_map('intval', array_keys($connections));

        $this->logger->info('[WS Registry] Found FDs for job', [
            'job_id' => $jobId,
            'fds' => $fds,
            'count' => count($fds),
        ]);

        return $fds;
    }

    public function getJobIdByFd(int $fd): ?string
    {
        $jobId = $this->redis->get(self::REDIS_FD_TO_JOB_PREFIX . $fd);
        return $jobId !== false ? (string)$jobId : null;
    }

    public function getConnectionCount(string $jobId): int
    {
        return $this->redis->hLen(self::REDIS_KEY_PREFIX . $jobId);
    }

    public function removeAllConnectionsForJob(string $jobId): int
    {
        $count = $this->getConnectionCount($jobId);

        if ($count > 0) {
            $connections = $this->redis->hGetAll(self::REDIS_KEY_PREFIX . $jobId);
            foreach (array_keys($connections) as $fd) {
                $this->redis->del(self::REDIS_FD_TO_JOB_PREFIX . $fd);
            }

            $this->redis->del(self::REDIS_KEY_PREFIX . $jobId);

            $this->logger->info('[WS Registry] Removed all connections for job', [
                'job_id' => $jobId,
                'removed_count' => $count,
            ]);
        }

        return $count;
    }
}
