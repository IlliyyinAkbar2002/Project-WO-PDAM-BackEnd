<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DetailFormController;
use App\Http\Controllers\JenisLokasiController;
use App\Http\Controllers\JenisWorkorderController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LemburSplController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkorderActionController;
use App\Http\Controllers\WorkorderController;
use App\Http\Controllers\ProgressWorkorderController;
use App\Http\Controllers\DetailProgressController;
use App\Http\Controllers\MasterLocationController;
use App\Http\Controllers\PegawaiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Multi-Client Architecture
|--------------------------------------------------------------------------
|
| Structure: /api/{version}/{client}/{resource}
| - Versioned from the start (v1)
| - Client-specific authentication and configuration
| - Shared business logic with client-aware responses
|
*/

// ============================================
// API Version 1
// ============================================
Route::prefix('v1')->group(function () {
    
    // ----------------------------------------
    // Mobile Client Routes
    // ----------------------------------------
    Route::prefix('mobile')->group(function () {
        // Public mobile auth
        Route::post('login', [AuthController::class, 'mobileLogin']);
        Route::post('register', [AuthController::class, 'mobileRegister']);
        
        // Protected mobile routes
        Route::middleware(['auth:sanctum', 'client.mobile'])->group(function () {
            Route::post('logout', [AuthController::class, 'mobileLogout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });
    
    // ----------------------------------------
    // Web Client Routes (future)
    // ----------------------------------------
    Route::prefix('web')->group(function () {
        Route::post('login', [AuthController::class, 'webLogin']);
        
        Route::middleware(['auth:sanctum', 'client.web'])->group(function () {
            Route::post('logout', [AuthController::class, 'webLogout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });
    
    // ----------------------------------------
    // Shared Protected Routes
    // ----------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        // Workorder resources
        Route::apiResource('workorder', WorkorderController::class);
        
        // KPI and reporting
        Route::get('kpi', [KpiController::class, 'index']);
        
        // Master data
        Route::apiResource('jenis-workorder', JenisWorkorderController::class);
        Route::apiResource('jenis-lokasi', JenisLokasiController::class);
        Route::get('user', [UserController::class, 'index']);
        Route::get('pegawai', [PegawaiController::class, 'index']);
        
        // Location management
        Route::get('master-location', [MasterLocationController::class, 'index']);
        Route::post('master-location', [MasterLocationController::class, 'store']);
        
        // Progress tracking
        Route::apiResource('progress-workorder', ProgressWorkorderController::class);
        Route::apiResource('detail-progress', DetailProgressController::class);
        Route::post('progress-workorder/manual-run', [ProgressWorkorderController::class, 'manualRun']);
        
        // Lembur SPL
        Route::apiResource('lembur-spl', LemburSplController::class);
        
        // Workorder actions
        Route::get('workorder-action', [WorkorderActionController::class, 'index']);
        
        // Detail form
        Route::get('detail-form', [DetailFormController::class, 'index']);
    });
});

// ============================================
// Health Check (unversioned)
// ============================================
Route::get('ping', function () {
    return response()->json([
        'message' => 'API Laravel Connected!',
        'version' => 'v1',
        'timestamp' => now()->toISOString()
    ]);
});
