<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\JenisWorkorderController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LemburSplController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkorderController;
use App\Http\Controllers\ProgressWorkorderController;
use App\Http\Controllers\ProgressDetailController;
use App\Http\Controllers\ProgressLemburController;
use App\Http\Controllers\ProgressDetailLemburController;
use App\Http\Controllers\LaporanWorkorderController;
use App\Http\Controllers\MasterLocationController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PengaduanController;
use App\Http\Controllers\AssignmentWorkorder;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\WoPeminjamanMaterialController;
use Illuminate\Support\Facades\Route;


// ============================================
// API Version 1
// ============================================
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        // Public auth
        Route::post('login', [AuthController::class, 'AuthLogin']);
        Route::post('register', [AuthController::class, 'AuthRegister']);

        Route::post('check-email', [AuthController::class, 'checking_email']);
        Route::post('new-password', [AuthController::class, 'new_password']);
        
        // Protected auth routes
        Route::middleware(['auth:sanctum', 'client.valid'])->group(function () {
            Route::post('logout', [AuthController::class, 'AuthLogout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });
    

    Route::middleware('auth:sanctum')->group(function () {
        // Workorder resources Web NextJS and Flutter Mobile
        Route::get('workorder/history',[WorkorderController::class, 'history']);
        Route::get('workorder/history/{id}',[WorkorderController::class, 'historyDetail']);
        Route::get('workorder/history/export-pdf', [WorkorderController::class, 'exportPdf']);
        Route::apiResource('workorder', WorkorderController::class);
        Route::post('workorder', [WorkorderController::class, 'store']);
        Route::patch('workorder/{id}/status', [WorkorderController::class, 'updateStatus']);
        Route::patch('workorder/{id}/toggle-status',[WorkorderController::class, 'toggleStatus']);

        // Assignment Workorder Mobile Flutter
        Route::get('workorder/{id}/assignment', [AssignmentWorkorder::class, 'show']);
        Route::post('workorder/{id}/assign-staff', [AssignmentWorkorder::class, 'assignStaff']);
        
        // KPI
        Route::get('kpi', [KpiController::class, 'index']);
        Route::get('kpi/departemen/{id}', [KpiController::class, 'departemen']);

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


        
        // notification
        // [S] Notifikasi — Laravel Database Notification Flutter Mobile.
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::match(['put', 'post'], 'notifications/{id}/read', [NotificationController::class, 'markAsRead']);

        // Location management Web NextJS
        Route::get('master-location', [MasterLocationController::class, 'index']);
        Route::post('master-location', [MasterLocationController::class, 'store']);
        // monitoring lokasi user (untuk admin) Web NextJS
        // Route::get('workorder/{id}/location-members', [LocationController::class, 'membersAtLocation']);
        
        // Progress tracking Flutter Mobile and Web NextJS
        // Segmen literal didefinisikan SEBELUM {id} agar tidak tertangkap sebagai id.
        Route::get('progress-workorder', [ProgressWorkorderController::class, 'index']);
        Route::post('progress-workorder/manual-run', [ProgressWorkorderController::class, 'manualRun']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/start', [ProgressWorkorderController::class, 'start']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/submit', [ProgressWorkorderController::class, 'submit']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/review', [ProgressWorkorderController::class, 'review']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/resubmit', [ProgressWorkorderController::class, 'resubmit']);
        Route::get('progress-workorder/by-member/{workorderId}', [ProgressWorkorderController::class, 'progressByMember'])->whereNumber('workorderId');
        Route::get('progress-workorder/member-summary/{workorderId}', [ProgressWorkorderController::class, 'memberSummary'])->whereNumber('workorderId');
        Route::get('progress-workorder/{id}', [ProgressWorkorderController::class, 'show'])->whereNumber('id');
        Route::match(['post', 'put', 'patch'], 'progress-workorder/{id}', [ProgressWorkorderController::class, 'update'])->whereNumber('id');
        // Monitoring Progress — untuk dashboard Superadmin Web NextJS, menampilkan semua progress dengan filter lebih lengkap (status, departemen, tanggal, dll).
        Route::get('progress-workorder/monitoring', [ProgressWorkorderController::class, 'monitoring']);


        // Progress workorder lembur
        Route::match(['post', 'put', 'patch'], 'progress-lembur/start', [ProgressLemburController::class, 'start']);
        Route::match(['post', 'put', 'patch'], 'progress-lembur/submit', [ProgressLemburController::class, 'submit']);
        Route::match(['post', 'put', 'patch'], 'progress-lembur/review', [ProgressLemburController::class, 'review']);
        Route::match(['post', 'put', 'patch'], 'progress-lembur/resubmit', [ProgressLemburController::class, 'resubmit']);
        Route::get('progress-lembur/by-member/{workorderId}', [ProgressLemburController::class, 'progressByMember'])->whereNumber('workorderId');
        Route::get('progress-lembur/member-summary/{workorderId}', [ProgressLemburController::class, 'memberSummary'])->whereNumber('workorderId');
        Route::get('progress-lembur/{id}', [ProgressLemburController::class, 'show'])->whereNumber('id');
        Route::match(['post', 'put', 'patch'], 'progress-lembur/{id}', [ProgressLemburController::class, 'update'])->whereNumber('id');
        // Riwayat review progress lembur (read-only) — SPV melihat siklus review
        Route::get('progress-detail-lembur', [ProgressDetailLemburController::class, 'index']);
        Route::get('progress-detail-lembur/{id}', [ProgressDetailLemburController::class, 'show'])->whereNumber('id');

        
        // Progress Detail — riwayat review (read-only) Flutter Mobile and Web NextJS
        Route::get('progress-detail', [ProgressDetailController::class, 'index']);
        Route::get('progress-detail/{id}', [ProgressDetailController::class, 'show'])->whereNumber('id');

        // Laporan Workorder — auto-generate saat SPV approve review Flutter Mobile and Web NextJS
        Route::apiResource('laporan-workorder', LaporanWorkorderController::class)->only(['index', 'show', 'store']);
        
        // Lembur SPL Flutter Mobile and Web NextJS
        // apiResource sudah mencakup: index, store (POST), show, update (PUT|PATCH), destroy.
        // SPV ajukan lembur → POST; Superadmin approve/reject → PUT|PATCH /{id} (auto-assign staff).
        Route::apiResource('lembur-spl', LemburSplController::class);
        

        // Pengaduan Web NextJS
        Route::get('pengaduan/options', [PengaduanController::class, 'options']);
        Route::apiResource('pengaduan', PengaduanController::class);

        // Jenis Work Order Web NextJS
        Route::apiResource('jenis-workorder', JenisWorkorderController::class);
        Route::get('jenis-workorder/{id}', [JenisWorkorderController::class, 'show']);
        Route::patch('jenis-workorder/{id}/status', [JenisWorkorderController::class, 'updateStatus']);

        // Peminjaman Material per WO — Flutter Mobile
        Route::get('log-peminjaman-material', [WoPeminjamanMaterialController::class, 'index']);
        Route::get('workorder/{id}/peminjaman-material', [WoPeminjamanMaterialController::class, 'show']);
        Route::post('workorder/{id}/peminjaman-material', [WoPeminjamanMaterialController::class, 'pinjam']);
        Route::post('peminjaman-material/{id}/kembalikan', [WoPeminjamanMaterialController::class, 'kembalikan']);
        Route::post('peminjaman-material/{id}/verify', [WoPeminjamanMaterialController::class, 'verify']);

        // Master Material Web NextJS
        Route::get('material/generate-code', [MaterialController::class, 'generateCode']);
        Route::put('material/{kode_material}/edit', [MaterialController::class, 'edit']);
        Route::apiResource('material', MaterialController::class)->except(['update']);
        Route::post('material', [MaterialController::class, 'store']);
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