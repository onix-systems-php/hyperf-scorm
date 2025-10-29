<?php
declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\Controller\WebSocket;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Redis\Redis;
use Hyperf\WebSocketServer\Context;
use Psr\Log\LoggerInterface;
use function Hyperf\Config\config;

class ScormProgressWebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    private const REDIS_KEY_PREFIX = 'ws_job_connections:';
    private const REDIS_FD_TO_JOB_PREFIX = 'ws_fd_to_job:';

    public function onMessage($server, $frame): void
    {
        if ($frame->data === 'ping') {
            $server->push($frame->fd, json_encode(['type' => 'pong']));
            return;
        }

        $data = json_decode($frame->data, true);

        if (!is_array($data)) {
            return; // Invalid JSON, ignore silently
        }

        if (isset($data['action']) && $data['action'] === 'subscribe' && isset($data['job_id'])) {
            if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $data['job_id'])) {
                $server->push($frame->fd, json_encode([
                    'type' => 'error',
                    'message' => 'Invalid job_id format. Expected UUID.',
                ]));
                return;
            }

            $redis = ApplicationContext::getContainer()->get(Redis::class);
            $redis->hSet(self::REDIS_KEY_PREFIX . $data['job_id'], (string)$frame->fd, time());
            $redis->set(
                self::REDIS_FD_TO_JOB_PREFIX . $frame->fd,
                $data['job_id'],
                ['EX' => config('scorm.redis.ttl.websocket', 86400)]
            );

            $server->push($frame->fd, json_encode([
                'type' => 'subscribed',
                'job_id' => $data['job_id'],
            ]));
        }
    }

    public function onOpen($server, $request): void
    {
        $logger = ApplicationContext::getContainer()->get(LoggerInterface::class);

        $queryString = $request->server['query_string'] ?? '';
        $params = [];
        parse_str($queryString, $params);
        $jobId = $params['job_id'] ?? null;

        $logger->info('[WS Debug] Client connecting', [
            'fd' => $request->fd,
            'job_id' => $jobId,
            'query_string' => $queryString,
        ]);

        if ($jobId && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $jobId)) {
            $logger->warning('[WS Debug] Invalid job_id format', [
                'fd' => $request->fd,
                'job_id' => $jobId,
            ]);

            $server->push($request->fd, json_encode([
                'type' => 'error',
                'message' => 'Invalid job_id format. Expected UUID.',
            ]));
            $server->close($request->fd);
            return;
        }

        if ($jobId) {
            $redis = ApplicationContext::getContainer()->get(Redis::class);

            $redis->hSet(self::REDIS_KEY_PREFIX . $jobId, (string)$request->fd, time());
            $redis->set(
                self::REDIS_FD_TO_JOB_PREFIX . $request->fd,
                $jobId,
                ['EX' => config('scorm.redis.ttl.websocket', 86400)]
            );

            $allConnections = $redis->hGetAll(self::REDIS_KEY_PREFIX . $jobId);

            $logger->info('[WS Debug] Connection registered in Redis', [
                'fd' => $request->fd,
                'job_id' => $jobId,
                'total_connections' => count($allConnections),
                'all_connections' => $allConnections,
            ]);

            $server->push($request->fd, json_encode([
                'type' => 'connected',
                'job_id' => $jobId,
                'message' => 'WebSocket connection established',
            ]));

            Context::set('job_id', $jobId);
        } else {
            $logger->error('[WS Debug] Missing job_id parameter', [
                'fd' => $request->fd,
            ]);

            $server->push($request->fd, json_encode([
                'type' => 'error',
                'message' => 'job_id parameter is required',
            ]));
            $server->close($request->fd);
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        $logger = ApplicationContext::getContainer()->get(LoggerInterface::class);
        $redis = ApplicationContext::getContainer()->get(Redis::class);

        $jobId = $redis->get(self::REDIS_FD_TO_JOB_PREFIX . $fd);

        if ($jobId) {
            $redis->hDel(self::REDIS_KEY_PREFIX . $jobId, (string)$fd);
            $redis->del(self::REDIS_FD_TO_JOB_PREFIX . $fd);

            // Get remaining connections
            $remainingConnections = $redis->hGetAll(self::REDIS_KEY_PREFIX . $jobId);

            $logger->info('[WS Debug] Connection closed and removed from Redis', [
                'fd' => $fd,
                'job_id' => $jobId,
                'remaining_connections' => count($remainingConnections),
            ]);

            if (empty($remainingConnections)) {
                $redis->del(self::REDIS_KEY_PREFIX . $jobId);
            }
        } else {
            $logger->info('[WS Debug] Connection closed (no job_id found)', [
                'fd' => $fd,
            ]);
        }
    }

    public static function getSubscribedFds(string $jobId): array
    {
        $logger = ApplicationContext::getContainer()->get(LoggerInterface::class);
        $redis = ApplicationContext::getContainer()->get(Redis::class);

        $connections = $redis->hGetAll(self::REDIS_KEY_PREFIX . $jobId);

        $logger->info('[WS Debug] Getting subscribed FDs from Redis', [
            'search_job_id' => $jobId,
            'redis_key' => self::REDIS_KEY_PREFIX . $jobId,
            'all_connections' => $connections,
            'total_connections' => count($connections),
        ]);

        $fds = array_map('intval', array_keys($connections));

        $logger->info('[WS Debug] Found FDs for job from Redis', [
            'job_id' => $jobId,
            'fds' => $fds,
            'count' => count($fds),
        ]);

        return $fds;
    }
}
