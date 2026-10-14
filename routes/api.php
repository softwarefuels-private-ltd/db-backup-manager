<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupRunController;
use App\Http\Controllers\Api\BackupScheduleController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DatabaseConnectionController;
use App\Http\Controllers\Api\StorageDestinationController;
use App\Http\Controllers\Api\SystemStatusController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('v1/auth')->group(function () {
    Route::get('/setup-status', [AuthController::class, 'setupStatus']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected endpoints
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Authenticated user & session
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard analytics & system requirements
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/system/status', [SystemStatusController::class, 'status']);

    // Database connections CRUD & connectivity test
    Route::post('connections/health-check', [DatabaseConnectionController::class, 'healthCheckAll']);
    Route::apiResource('connections', DatabaseConnectionController::class);
    Route::post('connections/{connection}/test', [DatabaseConnectionController::class, 'test']);

    // Storage destinations CRUD & write test
    Route::apiResource('destinations', StorageDestinationController::class);
    Route::post('destinations/{destination}/test', [StorageDestinationController::class, 'test']);

    // Backup schedules & manual trigger
    Route::apiResource('schedules', BackupScheduleController::class);
    Route::post('schedules/{schedule}/trigger', [BackupScheduleController::class, 'trigger']);

    // Backup runs history, streaming downloads, deletion
    Route::get('runs', [BackupRunController::class, 'index']);
    Route::get('runs/{run}', [BackupRunController::class, 'show']);
    Route::get('runs/{run}/download', [BackupRunController::class, 'download']);
    Route::delete('runs/{run}', [BackupRunController::class, 'destroy']);
});
