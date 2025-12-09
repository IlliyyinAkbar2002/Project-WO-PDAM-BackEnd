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


// ============================================
// API Version 1
// ============================================
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // Public auth
        Route::post('login', [AuthController::class, 'AuthLogin']);
        Route::post('register', [AuthController::class, 'AuthRegister']);
        
        
        // Protected auth routes
        Route::middleware(['auth:sanctum', 'client.valid'])->group(function () {
            Route::post('logout', [AuthController::class, 'AuthLogout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });
    

    Route::middleware('auth:sanctum')->group(function () {
        // Workorder resources
        Route::apiResource('workorder', WorkorderController::class);
        Route::post('workorder', [WorkorderController::class, 'store']);
        
        // KPI and reporting
        Route::get('kpi', [KpiController::class, 'index']);
        
        // Master data
        Route::apiResource('jenis-workorder', JenisWorkorderController::class);
        Route::apiResource('jenis-lokasi', JenisLokasiController::class);
        Route::get('user', [UserController::class, 'index']);
        Route::get('pegawai', [PegawaiController::class, 'index']);
        Route::get('pegawai/filter', [PegawaiController::class, 'getPegawaiByFilter']);
        
        // Location management
        Route::get('master-location', [MasterLocationController::class, 'index']);
        Route::post('master-location', [MasterLocationController::class, 'store']);
        
        // Progress tracking
        Route::apiResource('progress-workorder', ProgressWorkorderController::class);
        Route::apiResource('detail-progress', DetailProgressController::class);
        Route::post('progress-workorder/manual-run', [ProgressWorkorderController::class, 'manualRun']);
        
        // Lembur SPL
        Route::apiResource('lembur-spl', LemburSplController::class);
        Route::post('lembur-spl', [LemburSplController::class, 'store']);
        Route::put('lembur-spl/{id}', [LemburSplController::class, 'update']);

        // Jenis Work Order
        Route::get('jenis-workorder/{id}', [JenisWorkorderController::class, 'show']);
        Route::put('jenis-workorder/{id}', [JenisWorkorderController::class, 'update']);
        Route::post('jenis-workorder', [JenisWorkorderController::class, 'store']);
        
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
