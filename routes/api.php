<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DetailFormController;
use App\Http\Controllers\FormWorkorderController;
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
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PengaduanController;
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
        Route::patch('workorder/{id}/status', [WorkorderController::class, 'updateStatus']);
        Route::patch('workorder/{id}/toggle-status',[WorkorderController::class, 'toggleStatus']);
        
        // KPI
        Route::get('kpi', [KpiController::class, 'index']);
        Route::get('kpi/departemen/{id}', [KpiController::class, 'departemen']);

        Route::apiResource('jenis-lokasi', JenisLokasiController::class);

        // User & Pegawai
        Route::get('users', [UserController::class, 'index']);
        Route::post('users/{id}/reset-password',[UserController::class, 'resetPassword']);
        Route::patch('users/{id}/toggle-status', [UserController::class, 'toggleAccountStatus']);
        
        Route::get('pegawai', [PegawaiController::class, 'index']);
        Route::post('pegawai-user-create', [PegawaiController::class, 'store']);
        Route::get('pegawai/meta', [PegawaiController::class, 'meta']);
        Route::get('pegawai/meta/filter-options', [PegawaiController::class, 'filterOptions']);
        Route::get('pegawai/filter', [PegawaiController::class, 'getPegawaiByFilter']);
        Route::get('pegawai/{pegawai}', [PegawaiController::class, 'show'])
            ->whereNumber('pegawai');

        Route::put('pegawai/{pegawai}', [PegawaiController::class, 'update'])
            ->whereNumber('pegawai');

        Route::delete('pegawai/{pegawai}', [PegawaiController::class, 'destroy'])
            ->whereNumber('pegawai');

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

        // Pengaduan
        Route::apiResource('pengaduan', PengaduanController::class);

        // Jenis Work Order
        Route::apiResource('jenis-workorder', JenisWorkorderController::class);
        Route::get('jenis-workorder/{id}', [JenisWorkorderController::class, 'show']);
        Route::patch('jenis-workorder/{id}/status', [JenisWorkorderController::class, 'updateStatus']);

        // Workorder actions
        Route::get('workorder-action', [WorkorderActionController::class, 'index']);

        // Master Material
        Route::apiResource('material', MaterialController::class);
        Route::post('material', [MaterialController::class, 'store']);
        Route::patch('material/{kode_material}/pakai', [MaterialController::class, 'update']);
        Route::put('material/{kode_material}/edit', [MaterialController::class, 'edit']);
        Route::get('material/generate-code', [MaterialController::class, 'generateCode']);
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

// // Form Workorder
        // Route::get('jenis-workorder/{id}/form-workorder', [FormWorkorderController::class, 'index']);
        // Route::post('jenis-workorder/{id}/form-workorder', [FormWorkorderController::class, 'store']);
        // Route::get('jenis-workorder/{jenis_workorder_id}/form-workorder/{id}', [FormWorkorderController::class, 'show']);
        // Route::put('jenis-workorder/{jenis_workorder_id}/form-workorder/{id}', [FormWorkorderController::class, 'update']);
        // Route::delete('jenis-workorder/{jenis_workorder_id}/form-workorder/{id}', [FormWorkorderController::class, 'destroy']);

        // // Detail form (opsi dropdown)
        // Route::get('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form', [DetailFormController::class, 'index']);
        // Route::post('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form', [DetailFormController::class, 'store']);
        // Route::get('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form/{id}', [DetailFormController::class, 'show']);
        // Route::put('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form/{id}', [DetailFormController::class, 'update']);
        // Route::delete('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form/{id}', [DetailFormController::class, 'destroy']);