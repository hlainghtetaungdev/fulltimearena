<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProxyController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SuperController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/public/bootstrap', [PublicController::class, 'bootstrap']);
Route::get('/proxy/{source}', [ProxyController::class, 'show'])->middleware('throttle:120,1')->whereIn('source', ['live', 'result', 'news', 'market', 'odds']);

Route::prefix('user')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'userLogin'])->middleware('throttle:10,1');
    Route::post('/auth/signup', [AuthController::class, 'signup'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'role:user'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/notifications', [UserController::class, 'notifications']);
        Route::get('/predictions', [UserController::class, 'predictions']);
        Route::post('/predictions', [UserController::class, 'submitPrediction']);
        Route::get('/unit-requests', [UserController::class, 'unitRequests']);
        Route::post('/unit-requests', [UserController::class, 'submitUnitRequest']);
    });
});

Route::prefix('agent')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'agentLogin'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'role:agent'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', [AgentController::class, 'dashboard']);
        Route::get('/users', [AgentController::class, 'users']);
        Route::get('/unit-requests', [AgentController::class, 'requests']);
        Route::put('/unit-requests/{unitRequest}', [AgentController::class, 'updateRequest']);
        Route::post('/notifications', [AgentController::class, 'sendNotification']);
        Route::get('/{resource}', [AgentController::class, 'section'])->whereIn('resource', ['payments', 'providers', 'categories', 'ibet_rules', 'contact', 'notifications']);
    });
});

Route::prefix('super')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'superLogin'])->middleware('throttle:10,1');
    Route::middleware(['auth:sanctum', 'role:super'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', [SuperController::class, 'dashboard']);
        Route::get('/agents', [SuperController::class, 'agents']);
        Route::post('/agents', [SuperController::class, 'saveAgent']);
        Route::put('/agents/{staff}', [SuperController::class, 'saveAgent']);
        Route::match(['get', 'put'], '/settings', [SuperController::class, 'settings']);
        Route::get('/{resource}', [SuperController::class, 'collection'])->whereIn('resource', ['ads', 'categories', 'live_matches', 'notifications', 'submissions']);
        Route::post('/{resource}', [SuperController::class, 'store'])->whereIn('resource', ['ads', 'categories', 'live_matches', 'notifications', 'submissions']);
        Route::put('/{resource}/{id}', [SuperController::class, 'update'])->whereIn('resource', ['ads', 'categories', 'live_matches', 'notifications', 'submissions']);
        Route::delete('/{resource}/{id}', [SuperController::class, 'destroy'])->whereIn('resource', ['ads', 'categories', 'live_matches', 'notifications', 'submissions']);
    });
});
