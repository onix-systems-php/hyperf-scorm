<?php

declare(strict_types=1);

namespace OnixSystemsPHP\HyperfScorm\WebSocket;

use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\WebSocketServer\Context;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class ScormProgressWebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    private static array $fdToJobId = [];

    public function onMessage($server, $frame): void
    {
        // Клиент может отправлять ping для keep-alive
        if ($frame->data === 'ping') {
            $server->push($frame->fd, json_encode(['type' => 'pong']));
            return;
        }

        $data = json_decode($frame->data, true);

        if (!is_array($data)) {
            return; // Invalid JSON, ignore silently
        }

        if (isset($data['action']) && $data['action'] === 'subscribe' && isset($data['job_id'])) {
            // Validate job_id format (UUID)
            if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $data['job_id'])) {
                $server->push($frame->fd, json_encode([
                    'type' => 'error',
                    'message' => 'Invalid job_id format. Expected UUID.',
                ]));
                return;
            }

            self::$fdToJobId[$frame->fd] = $data['job_id'];
            $server->push($frame->fd, json_encode([
                'type' => 'subscribed',
                'job_id' => $data['job_id'],
            ]));
        }
    }

    public function onOpen($server, $request): void
    {
        $queryString = $request->server['query_string'] ?? '';
        $params = [];
        parse_str($queryString, $params);
        $jobId = $params['job_id'] ?? null;

        // Validate job_id format (UUID)
        if ($jobId && !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $jobId)) {
            $server->push($request->fd, json_encode([
                'type' => 'error',
                'message' => 'Invalid job_id format. Expected UUID.',
            ]));
            $server->close($request->fd);
            return;
        }

        if ($jobId) {
            self::$fdToJobId[$request->fd] = $jobId;

            $server->push($request->fd, json_encode([
                'type' => 'connected',
                'job_id' => $jobId,
                'message' => 'WebSocket connection established',
            ]));

            Context::set('job_id', $jobId);
        } else {
            $server->push($request->fd, json_encode([
                'type' => 'error',
                'message' => 'job_id parameter is required',
            ]));
            $server->close($request->fd);
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        unset(self::$fdToJobId[$fd]);
    }

    public static function getSubscribedFds(string $jobId): array
    {
        $fds = [];
        foreach (self::$fdToJobId as $fd => $subscribedJobId) {
            if ($subscribedJobId === $jobId) {
                $fds[] = $fd;
            }
        }
        return $fds;
    }
}
