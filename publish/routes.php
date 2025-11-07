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

// SCORM Package Management Routes
Router::addGroup('/v1/scorm/packages', function () {
    Router::post('/upload', [ScormController::class, 'upload']);
    Router::post('', [ScormController::class, 'create']);
    Router::get('', [ScormController::class, 'index']);
    Router::get('/{id:\d+}', [ScormController::class, 'show']);
    Router::delete('/{id:\d+}', [ScormController::class, 'destroy']);
});

// SCORM API Routes
Router::addGroup('/v1/api/scorm', function () {
    Router::get('/{packageId:\d+}/initialize', [ScormApiController::class, 'initialize']);
    Router::post('/{packageId:\d+}/commit/{sessionToken}', [ScormApiController::class, 'commit']);
});

// SCORM Player Routes
Router::addGroup('/v1/api/scorm/player', function () {
    Router::get('/{packageId:\d+}/launch', [ScormPlayerController::class, 'launch']);
});

// SCORM Job Status Routes
Router::addGroup('/v1/scorm/jobs', function () {
    Router::get('/{jobId}/status', [ScormJobStatusController::class, 'status']);
    Router::post('/batch-status', [ScormJobStatusController::class, 'getBatchStatus']);
    Router::post('/{jobId}/cancel', [ScormJobStatusController::class, 'cancelJob']);
});

// WebSocket Routes для SCORM progress tracking
// WebSocket handshake route - must be registered on WebSocket server
// This route is handled by the socket-io server defined in config/autoload/server.php
Router::addServer('socket-io', function () {
    Router::get('/scorm-progress', ScormProgressWebSocketController::class);
});
