<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;
use OnixSystemsPHP\HyperfScorm\Controller\ScormApiController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormJobStatusController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController;
use OnixSystemsPHP\HyperfScorm\Controller\WebSocket\ScormProgressWebSocketController;
use function \Hyperf\Config\config;

Router::addGroup('/v1/scorm/packages', function () {
    Router::post('/upload', [ScormController::class, 'upload']);
    Router::post('', [ScormController::class, 'create']);
    Router::get('', [ScormController::class, 'index']);
    Router::get('/{id:\d+}', [ScormController::class, 'show']);
    Router::delete('/{id:\d+}', [ScormController::class, 'destroy']);
});

Router::addGroup('/v1/api/scorm', function () {
    Router::get('/{packageId:\d+}/initialize', [ScormApiController::class, 'initialize']);
    Router::post('/{packageId:\d+}/commit/{sessionToken}', [ScormApiController::class, 'commit']);
});

Router::addGroup('/v1/api/scorm/player', function () {
    Router::get('/{packageId:\d+}/launch', [ScormPlayerController::class, 'launch']);
});

Router::addGroup('/v1/scorm/jobs', function () {
    Router::get('/{jobId}/status', [ScormJobStatusController::class, 'status']);
    Router::post('/batch-status', [ScormJobStatusController::class, 'getBatchStatus']);
    Router::post('/{jobId}/cancel', [ScormJobStatusController::class, 'cancelJob']);
});

Router::addServer(config('scorm.ws.name'), function () {
    Router::get('/scorm-progress', ScormProgressWebSocketController::class);
});
