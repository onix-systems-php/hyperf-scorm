<?php
declare(strict_types=1);

/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;
use OnixSystemsPHP\HyperfScorm\Controller\ScormApiController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormJobStatusController;
use OnixSystemsPHP\HyperfScorm\Controller\WebSocket\ScormProgressWebSocketController;
use function Hyperf\Config\config;

Router::addGroup('/v1/api/scorm', function () {
    Router::get('/{packageId:\d+}/users/{userId}/initialize', [ScormApiController::class, 'initialize']);
    Router::post('/{packageId:\d+}/commit/{sessionToken}', [ScormApiController::class, 'commit']);
});

Router::addGroup('/v1/scorm/jobs', function () {
    Router::get('/{jobId}/status', [ScormJobStatusController::class, 'status']);
    Router::post('/{jobId}/cancel', [ScormJobStatusController::class, 'cancelJob']);
});

Router::addServer(config('scorm.ws.name'), function () {
    Router::get('/scorm-progress', ScormProgressWebSocketController::class);
});
