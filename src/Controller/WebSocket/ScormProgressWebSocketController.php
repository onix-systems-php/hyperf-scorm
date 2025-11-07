<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfScorm\Controller\WebSocket;

use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\WebSocketServer\Context;
use OnixSystemsPHP\HyperfScorm\Service\WebSocketConnectionService;
use Psr\Log\LoggerInterface;

class ScormProgressWebSocketController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{
    private WebSocketConnectionService $connectionRegistry;

    private LoggerInterface $logger;

    public function onMessage($server, $frame): void
    {
        $this->initializeDependencies();

        if ($frame->data === 'ping') {
            $server->push($frame->fd, json_encode(['type' => 'pong']));
            return;
        }

        $data = json_decode($frame->data, true);

        if (! is_array($data)) {
            return; // Invalid JSON, ignore silently
        }

        if (isset($data['action']) && $data['action'] === 'subscribe' && isset($data['job_id'])) {
            if (! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $data['job_id'])) {
                $server->push($frame->fd, json_encode([
                    'type' => 'error',
                    'message' => 'Invalid job_id format. Expected UUID.',
                ]));
                return;
            }

            $this->connectionRegistry->registerConnection($data['job_id'], $frame->fd);

            $server->push($frame->fd, json_encode([
                'type' => 'subscribed',
                'job_id' => $data['job_id'],
            ]));
        }
    }

    public function onOpen($server, $request): void
    {
        $this->initializeDependencies();

        $queryString = $request->server['query_string'] ?? '';
        $params = [];
        parse_str($queryString, $params);
        $jobId = $params['job_id'] ?? null;

        $this->logger->info('[WS Debug] Client connecting', [
            'fd' => $request->fd,
            'job_id' => $jobId,
            'query_string' => $queryString,
        ]);

        if ($jobId && ! preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $jobId)) {
            $this->logger->warning('[WS Debug] Invalid job_id format', [
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
            $this->connectionRegistry->registerConnection($jobId, $request->fd);

            $totalConnections = $this->connectionRegistry->getConnectionCount($jobId);

            $this->logger->info('[WS Debug] Connection registered', [
                'fd' => $request->fd,
                'job_id' => $jobId,
                'total_connections' => $totalConnections,
            ]);

            $server->push($request->fd, json_encode([
                'type' => 'connected',
                'job_id' => $jobId,
                'message' => 'WebSocket connection established',
            ]));

            Context::set('job_id', $jobId);
        } else {
            $this->logger->error('[WS Debug] Missing job_id parameter', [
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
        $this->initializeDependencies();

        $jobId = $this->connectionRegistry->getJobIdByFd($fd);

        if ($jobId) {
            $this->connectionRegistry->unregisterConnection($jobId, $fd);

            $remainingConnections = $this->connectionRegistry->getConnectionCount($jobId);

            $this->logger->info('[WS Debug] Connection closed and removed', [
                'fd' => $fd,
                'job_id' => $jobId,
                'remaining_connections' => $remainingConnections,
            ]);
        } else {
            $this->logger->info('[WS Debug] Connection closed (no job_id found)', [
                'fd' => $fd,
            ]);
        }
    }

    public static function getSubscribedFds(string $jobId): array
    {
        $container = ApplicationContext::getContainer();
        $registry = $container->get(WebSocketConnectionService::class);

        return $registry->getSubscribedFds($jobId);
    }

    /**
     * Initialize dependencies lazily using ApplicationContext
     * Note: WebSocket controllers cannot use constructor injection in Hyperf.
     */
    private function initializeDependencies(): void
    {
        if (! isset($this->connectionRegistry)) {
            $container = ApplicationContext::getContainer();
            $this->connectionRegistry = $container->get(WebSocketConnectionService::class);
            $this->logger = $container->get(LoggerInterface::class);
        }
    }
}
