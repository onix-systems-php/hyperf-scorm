<?php
declare(strict_types=1);

use Hyperf\HttpServer\Router\Router;
use OnixSystemsPHP\HyperfScorm\Controller\ScormController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController;
use OnixSystemsPHP\HyperfScorm\Controller\ScormApiController;

Router::addGroup('/v1/scorm/packages', function () {
    Router::post('/upload', [ScormController::class, 'upload']);
    Router::post('', [ScormController::class, 'create']);
    Router::get('', [ScormController::class, 'index']);
    Router::get('/{id:\d+}', [ScormController::class, 'show']);
    Router::delete('/{id:\d+}', [ScormController::class, 'delete']);
});
//// SCORM Player Routes
Router::addGroup('/v1/scorm/player', function () {
    Router::get('/{packageId:\d+}', [ScormPlayerController::class, 'launch']);
    Router::post('/{sessionId:\d+}/commit', [ScormApiController::class, 'commitCompact']);
    Router::post('/{sessionId:\d+}/initialize', [ScormApiController::class, 'initialize']);
//    Router::get('/{packageId:\d+}/data', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'getPlayerData']);
});
//
//// SCORM Session Management Routes
//Router::addGroup('/v1/scorm/sessions', function () {
//    Router::post('/start', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'startSession']);
//});
//
//// SCORM Attempt Management Routes
//Router::addGroup('/v1/scorm/attempts', function () {
//    Router::get('/{attemptId:\d+}', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'show']);
//    Router::post('/{attemptId:\d+}/suspend', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'suspend']);
//    Router::post('/{attemptId:\d+}/complete', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'complete']);
//    Router::get('/{attemptId:\d+}/cmi/{element}', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'getCmiValue']);
//    Router::post('/{attemptId:\d+}/cmi/{element}', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'setCmiValue']);
//    Router::get('/{attemptId:\d+}/cmi', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'getAllCmiData']);
//    Router::post('/{attemptId:\d+}/cmi/batch', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'batchUpdateCmi']);
//    Router::get('/{attemptId:\d+}/interactions', [\OnixSystemsPHP\HyperfScorm\Controller\ScormAttemptController::class, 'getInteractions']);
//});
//
// SCORM JavaScript API Routes (for SCORM content communication)
//use OnixSystemsPHP\HyperfScorm\Controller\ScormApiController;
//
//Router::addGroup('/v1/scorm/api', function () {
//    Router::post('/{attemptId:\d+}/initialize', [ScormApiController::class, 'initialize']);
//    Router::post('/{attemptId:\d+}/terminate', [ScormApiController::class, 'terminate']);
//    Router::post('/{attemptId:\d+}/commit', [ScormApiController::class, 'commit']);
//    Router::get('/{attemptId:\d+}/get-value/{element}', [ScormApiController::class, 'getValue']);
//    Router::post('/{attemptId:\d+}/set-value', [ScormApiController::class, 'setValue']);
//    Router::get('/{attemptId:\d+}/status', [ScormApiController::class, 'getStatus']);
//    Router::post('/{attemptId:\d+}/heartbeat', [ScormApiController::class, 'heartbeat']);
//});
//// Alternative SCORM API Routes (from ScormPlayerController)
//Router::addGroup('/v1/scorm/api', function () {
//    Router::post('/initialize', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'initializeApi']);
//    Router::post('/set-value', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'setValueApi']);
//    Router::post('/commit', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'commitApi']);
//    Router::post('/terminate', [\OnixSystemsPHP\HyperfScorm\Controller\ScormPlayerController::class, 'terminateApi']);
//});
