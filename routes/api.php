<?php

use App\Http\Controllers\Api\V1\Agent\ApmController;
use App\Http\Controllers\Api\V1\Agent\ConfigController;
use App\Http\Controllers\Api\V1\Agent\HeartbeatController;
use App\Http\Controllers\Api\V1\Agent\LogsController as AgentLogsController;
use App\Http\Controllers\Api\V1\Agent\MetricsController;
use App\Http\Controllers\Api\V1\Agent\RegisterController;
use App\Http\Controllers\Api\V1\Agent\ServicesController;
use App\Http\Controllers\Api\V1\AuthTokenController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthTokenController::class, 'store'])
        ->middleware('throttle:login');

    Route::post('agent/register', RegisterController::class)
        ->middleware('throttle:agent-register');

    Route::middleware(['agent', 'throttle:agent-heartbeat'])->group(function () {
        Route::post('agent/heartbeat', HeartbeatController::class);
        Route::get('agent/config', ConfigController::class);
    });

    Route::post('agent/metrics', MetricsController::class)
        ->middleware(['agent', 'throttle:agent-metrics']);

    Route::post('agent/logs', AgentLogsController::class)
        ->middleware(['agent', 'throttle:agent-logs']);

    Route::post('agent/apm', ApmController::class)
        ->middleware(['agent', 'throttle:agent-apm']);

    Route::post('agent/services', ServicesController::class)
        ->middleware(['agent', 'throttle:agent-apm']);

    Route::middleware(['auth:sanctum', 'active.user', 'tenant'])->group(function () {
        Route::post('auth/logout', [AuthTokenController::class, 'destroy']);
        Route::get('me', ProfileController::class);
        Route::apiResource('organizations', OrganizationController::class)
            ->except(['destroy'])
            ->names('api.v1.organizations');
        Route::apiResource('users', UserController::class)
            ->only(['index', 'store', 'show'])
            ->names('api.v1.users');
    });
});
