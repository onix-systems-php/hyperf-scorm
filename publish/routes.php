<?php
declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;
use OnixSystemsPHP\HyperfScorm\Controller\AsyncScormController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormApiController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController;

// Async SCORM Processing Routes
Router::addGroup('/v1/scorm/async', function () {
    Router::post('/upload', [AsyncScormController::class, 'uploadAsync']);
    Router::post('/upload-batch', [AsyncScormController::class, 'uploadBatchAsync']);
    Router::get('/status/{jobId}', [AsyncScormController::class, 'getStatus']);
    Router::post('/batch-status', [AsyncScormController::class, 'getBatchStatus']);
    Router::post('/cancel/{jobId}', [AsyncScormController::class, 'cancelJob']);
});

// SCORM Package Management Routes
Router::addGroup('/v1/scorm/packages', function () {
    Router::post('/upload', [ScormController::class, 'upload']);
    Router::post('', [ScormController::class, 'create']);
    Router::get('', [ScormController::class, 'index']);
    Router::get('/{id:\d+}', [ScormController::class, 'show']);
    Router::delete('/{id:\d+}', [ScormController::class, 'delete']);
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
