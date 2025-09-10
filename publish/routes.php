<?php
declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;
use OnixSystemsPHP\HyperfScorm\Controller\ScormApiController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController;

Router::addGroup('/v1/scorm/packages', function () {
    Router::post('/upload', [ScormController::class, 'upload']);
    Router::post('', [ScormController::class, 'create']);
    Router::get('', [ScormController::class, 'index']);
    Router::get('/{id:\d+}', [ScormController::class, 'show']);
    Router::delete('/{id:\d+}', [ScormController::class, 'delete']);
});

//// SCORM Player Routes
Router::addGroup('/v1/scorm-player', function () {
    Router::get('/package/{packageId:\d+}/launch', [ScormPlayerController::class, 'launch']);
    Router::addGroup('/package/{packageId:\d+}/session/{sessionToken}', function () {
        Router::get('/launch', [ScormPlayerController::class, 'launch']);
        Router::post('/commit', [ScormApiController::class, 'commit']);
        Router::post('/initialize', [ScormApiController::class, 'initialize']);
    });
});
//Router::addGroup('/v1/scorm/api', function () {
//    Router::post('/set-value', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'setValueApi']);
//    Router::post('/terminate', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'terminateApi']);
//});
