<?php

use Illuminate\Support\Facades\Route;
use NishangSystems\Passkeys\Http\Controllers\PasskeyController;

$routeConfig = config('passkeys.routes', []);
$middleware = $routeConfig['middleware'] ?? ['api'];
$authMiddleware = array_merge($middleware, $routeConfig['auth_middleware'] ?? ['auth']);
$prefix = $routeConfig['prefix'] ?? '';

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    Route::post('/auth/passkeys/options', [PasskeyController::class, 'loginOptions']);
    Route::post('/auth/passkeys', [PasskeyController::class, 'login']);
});

Route::group(['prefix' => $prefix, 'middleware' => $authMiddleware], function () {
    Route::post('/passkeys/options', [PasskeyController::class, 'registerOptions']);
    Route::post('/passkeys', [PasskeyController::class, 'register']);
    Route::delete('/passkeys/{id}', [PasskeyController::class, 'destroy']);
    Route::get('/passkeys', [PasskeyController::class, 'index']);
});
