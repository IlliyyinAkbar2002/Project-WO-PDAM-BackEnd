<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AssignmentWorkorder;
use App\Http\Controllers\JenisWorkorderController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LaporanWorkorderController;
use App\Http\Controllers\LemburSplController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkorderActionController;
use App\Http\Controllers\WorkorderController;
use App\Http\Controllers\ProgressWorkorderController;
use App\Http\Controllers\ProgressDetailController;
use App\Http\Controllers\ProgressLemburController;
use App\Http\Controllers\ProgressDetailLemburController;
use App\Http\Controllers\MasterLocationController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NotificationController;
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

        // Lupa password (publik, tanpa login) — alur sederhana: cek email lalu set password baru.
        Route::post('forgot-password', [AuthController::class, 'forgot_password']);
        Route::post('reset-password',  [AuthController::class, 'reset_password']);

        Route::middleware(['auth:sanctum', 'client.valid'])->group(function () {
            Route::post('logout',          [AuthController::class, 'AuthLogout']);
            Route::get('me',               [AuthController::class, 'me']);
            // Route::post('change-password', [AuthController::class, 'change_password']);
        });
    });

    // =========================================================
    // Protected API — wajib Bearer token (Sanctum)
    // =========================================================
    Route::middleware('auth:sanctum')->group(function () {

        // ROUTE FOR MOBILE
        Route::get('workorder/{id}/assignment', [AssignmentWorkorder::class, 'show']);
        Route::post('workorder/{id}/assign-staff', [AssignmentWorkorder::class, 'assignStaff']);

        Route::get('workorder-action',  [WorkorderActionController::class, 'index']);
        Route::post('workorder-action', [WorkorderActionController::class, 'store']);

        Route::get('progress-workorder', [ProgressWorkorderController::class, 'index']);
        Route::post('progress-workorder/manual-run', [ProgressWorkorderController::class, 'manualRun']);

        // [M] Mobile - SPV view individual member progress
        Route::get('progress-workorder/by-member/{workorderId}', [ProgressWorkorderController::class, 'progressByMember'])->whereNumber('workorderId');

        Route::match(['post', 'put', 'patch'], 'progress-workorder/start', [ProgressWorkorderController::class, 'start']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/submit', [ProgressWorkorderController::class, 'submit']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/review', [ProgressWorkorderController::class, 'review']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/resubmit', [ProgressWorkorderController::class, 'resubmit']);

        Route::get('progress-workorder/{id}', [ProgressWorkorderController::class, 'show'])->whereNumber('id');
        Route::match(['post', 'put', 'patch'], 'progress-workorder/{id}', [ProgressWorkorderController::class, 'update'])->whereNumber('id');
        Route::post('progress-workorder/{id}/cancel', [ProgressWorkorderController::class, 'cancel'])->whereNumber('id');

        // Progress Detail - review history tracking
        Route::get('progress-detail', [ProgressDetailController::class, 'index']);
        Route::get('progress-detail/{id}', [ProgressDetailController::class, 'show'])->whereNumber('id');
        Route::post('progress-detail/{id}/approve', [ProgressDetailController::class, 'approve'])->whereNumber('id');
        Route::post('progress-detail/{id}/reject', [ProgressDetailController::class, 'reject'])->whereNumber('id');

        Route::match(['post', 'put', 'patch'], 'progress-lembur/start', [ProgressLemburController::class, 'start']);
        Route::match(['post', 'put', 'patch'], 'progress-lembur/submit', [ProgressLemburController::class, 'submit']);
        Route::match(['post', 'put', 'patch'], 'progress-lembur/review', [ProgressLemburController::class, 'review']);
        Route::match(['post', 'put', 'patch'], 'progress-lembur/resubmit', [ProgressLemburController::class, 'resubmit']);

        Route::get('progress-lembur/by-member/{workorderId}', [ProgressLemburController::class, 'progressByMember'])->whereNumber('workorderId');
        Route::get('progress-lembur/member-summary/{workorderId}', [ProgressLemburController::class, 'memberSummary'])->whereNumber('workorderId');


        Route::get('progress-lembur/{id}', [ProgressLemburController::class, 'show'])->whereNumber('id');
        Route::match(['post', 'put', 'patch'], 'progress-lembur/{id}', [ProgressLemburController::class, 'update'])->whereNumber('id');
        Route::post('progress-lembur/{id}/cancel', [ProgressLemburController::class, 'cancel'])->whereNumber('id');

        // Riwayat review progress lembur (read-only) — SPV melihat siklus review
        Route::get('progress-detail-lembur', [ProgressDetailLemburController::class, 'index']);
        Route::get('progress-detail-lembur/{id}', [ProgressDetailLemburController::class, 'show'])->whereNumber('id');

        // Laporan Workorder
        Route::apiResource('laporan-workorder', LaporanWorkorderController::class)->only(['index', 'show', 'store']);

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
        Route::post('peminjaman-material/{id}/verify', [WoPeminjamanMaterialController::class, 'verify']);

        // [S] Notifikasi — Laravel Database Notification
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::match(['put', 'post'], 'notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        // ROUTE FOR WEB NEXTJS
        Route::apiResource('workorder', WorkorderController::class);
        // [W] Web Dashboard - member progress summary with statistics
        Route::get('progress-workorder/member-summary/{workorderId}', [ProgressWorkorderController::class, 'memberSummary'])->whereNumber('workorderId');
    });
});

Route::get('ping', function () {
    return response()->json([
        'message'   => 'API Laravel Connected!',
        'version'   => 'v1',
        'timestamp' => now()->toISOString(),
    ]);
});
