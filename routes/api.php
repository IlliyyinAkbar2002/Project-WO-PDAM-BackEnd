<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ApprovalWorkorder;
use App\Http\Controllers\AssignmentWorkorder;
use App\Http\Controllers\JenisWorkorderController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LemburSplController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkorderActionController;
use App\Http\Controllers\WorkorderController;
use App\Http\Controllers\ProgressWorkorderController;
use App\Http\Controllers\MasterLocationController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\WoPeminjamanMaterialController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|   [M] Mobile (Flutter)  — aplikasi petugas lapangan
|   [W] Web (Next.js)     — dashboard admin / SPV
|   [S] Shared            — dipakai keduanya
|
*/

Route::prefix('v1')->group(function () {

    // =========================================================
    // [S] AUTH — login/register publik, logout/me privat
    // =========================================================
    Route::prefix('auth')->group(function () {
        Route::post('login',    [AuthController::class, 'AuthLogin']);
        Route::post('register', [AuthController::class, 'AuthRegister']);

        Route::middleware(['auth:sanctum', 'client.valid'])->group(function () {
            Route::post('logout', [AuthController::class, 'AuthLogout']);
            Route::get('me',      [AuthController::class, 'me']);
        });
    });

    // =========================================================
    // Protected API — wajib Bearer token (Sanctum)
    // =========================================================
    Route::middleware('auth:sanctum')->group(function () {

        Route::apiResource('workorder', WorkorderController::class);
        Route::post('workorder/{id}/approve', [ApprovalWorkorder::class, 'approve']);
        Route::post('workorder/{id}/reject', [ApprovalWorkorder::class, 'reject']);
        Route::get('workorder/{id}/assignment', [AssignmentWorkorder::class, 'show']);
        Route::post('workorder/{id}/assign-staff', [AssignmentWorkorder::class, 'assignStaff']);


        Route::get('workorder-action',  [WorkorderActionController::class, 'index']);
        Route::post('workorder-action', [WorkorderActionController::class, 'store']);


        Route::get('progress-workorder', [ProgressWorkorderController::class, 'index']);
        Route::get('progress-workorder/quota/{id}', [ProgressWorkorderController::class, 'quota']);
        Route::post('progress-workorder/manual-run', [ProgressWorkorderController::class, 'manualRun']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/start', [ProgressWorkorderController::class, 'start']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/submit', [ProgressWorkorderController::class, 'submit']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/review', [ProgressWorkorderController::class, 'review']);

        Route::get('progress-workorder/{id}', [ProgressWorkorderController::class, 'show'])->whereNumber('id');
        Route::match(['post', 'put', 'patch'], 'progress-workorder/{id}', [ProgressWorkorderController::class, 'update'])->whereNumber('id');
        Route::post('progress-workorder/{id}/cancel', [ProgressWorkorderController::class, 'cancel'])->whereNumber('id');
       

        Route::apiResource('lembur-spl', LemburSplController::class);
        Route::apiResource('jenis-workorder', JenisWorkorderController::class);

        Route::get('user',            [UserController::class, 'index']);
        Route::get('pegawai',         [PegawaiController::class, 'index']);
        Route::get('pegawai/filter',  [PegawaiController::class, 'getPegawaiByFilter']);

        Route::get('master-location',       [MasterLocationController::class, 'index']);
        Route::post('master-location',      [MasterLocationController::class, 'store']);
        Route::get('master-location/{id}',  [MasterLocationController::class, 'show']);
        Route::put('master-location/{id}',  [MasterLocationController::class, 'update']);
        Route::delete('master-location/{id}', [MasterLocationController::class, 'destroy']);

        Route::middleware('role:superadmin')->group(function () {
            Route::patch('admin/pegawai/{id}/assign', [PegawaiController::class, 'assign']);
        });

        Route::get('kpi', [KpiController::class, 'index']);

        Route::apiResource('material', MaterialController::class);
        Route::patch('material/{kode_material}/pakai', [MaterialController::class, 'update']);
        Route::put('material/{kode_material}/edit',    [MaterialController::class, 'edit']);

        // Fitur Peminjaman & Pengembalian Material untuk Work Order
        Route::get('workorder/{id}/peminjaman-material', [WoPeminjamanMaterialController::class, 'index']);
        Route::post('workorder/{id}/peminjaman-material', [WoPeminjamanMaterialController::class, 'pinjam']);
        Route::post('peminjaman-material/{id}/kembalikan', [WoPeminjamanMaterialController::class, 'kembalikan']);
    });
});

Route::get('ping', function () {
    return response()->json([
        'message'   => 'API Laravel Connected!',
        'version'   => 'v1',
        'timestamp' => now()->toISOString(),
    ]);
});
