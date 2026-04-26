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
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| Semua route API di bawah adalah versi `v1`. Konsumen:
|
|   [M] Mobile (Flutter)  — aplikasi petugas lapangan
|   [W] Web (Next.js)     — dashboard admin / SPV
|   [S] Shared            — dipakai keduanya
|
| Tanda [M]/[W]/[S] di komentar hanya hint untuk developer. Authorization
| sebenarnya (role_id, kepemilikan data, dll.) diterapkan di sisi controller.
|
| Catatan refactor pasca TKT-01..07:
|   - `POST /workorder-action` ditambahkan (TKT-06). Sebelumnya hanya
|     `GET /workorder-action` yang terdaftar, padahal controller `store()`
|     sudah di-refactor untuk menginject `actor_id` dari auth user.
|   - Duplikasi route yang sudah tercover `apiResource` dihapus (supaya
|     `php artisan route:list` tidak menampilkan entri ganda). Tidak ada
|     perubahan perilaku untuk kedua FE.
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
        Route::post('workorder/{id}/approve', [WorkorderController::class, 'approve']);
        Route::post('workorder/{id}/reject', [WorkorderController::class, 'reject']);
        Route::post('workorder/{id}/assign-staff', [WorkorderController::class, 'assignStaff']);


        Route::get('workorder-action',  [WorkorderActionController::class, 'index']);
        Route::post('workorder-action', [WorkorderActionController::class, 'store']);

        Route::post('progress-workorder/manual-run', [ProgressWorkorderController::class, 'manualRun']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/start', [ProgressWorkorderController::class, 'start']);
        Route::match(['post', 'put', 'patch'], 'progress-workorder/submit', [ProgressWorkorderController::class, 'submit']);
        // Compatibility layer: terima method override legacy (_method PUT/PATCH) dari client lama.
        Route::match(['post', 'put', 'patch'], 'progress-workorder/review', [ProgressWorkorderController::class, 'review']);
        Route::apiResource('progress-workorder', ProgressWorkorderController::class)
            ->whereNumber('progress_workorder');
        Route::apiResource('detail-progress',    DetailProgressController::class);

        Route::apiResource('lembur-spl', LemburSplController::class);
        Route::apiResource('jenis-workorder', JenisWorkorderController::class);
        Route::apiResource('jenis-lokasi',    JenisLokasiController::class);

        Route::get('user',            [UserController::class, 'index']);
        Route::get('pegawai',         [PegawaiController::class, 'index']);
        Route::get('pegawai/filter',  [PegawaiController::class, 'getPegawaiByFilter']);

        Route::get('master-location',  [MasterLocationController::class, 'index']);
        Route::post('master-location', [MasterLocationController::class, 'store']);

        Route::middleware('role:superadmin')->group(function () {
            Route::patch('admin/pegawai/{id}/assign', [PegawaiController::class, 'assign']);
        });

        Route::get('jenis-workorder/{id}/form-workorder',           [FormWorkorderController::class, 'index']);
        Route::post('jenis-workorder/{id}/form-workorder',          [FormWorkorderController::class, 'store']);
        Route::get('jenis-workorder/{jenis_workorder_id}/form-workorder/{id}',    [FormWorkorderController::class, 'show']);
        Route::put('jenis-workorder/{jenis_workorder_id}/form-workorder/{id}',    [FormWorkorderController::class, 'update']);
        Route::delete('jenis-workorder/{jenis_workorder_id}/form-workorder/{id}', [FormWorkorderController::class, 'destroy']);

        Route::get('jenis-workorder/{jenis_workorder_id}/detail-form',           [DetailFormController::class, 'index']);
        Route::post('jenis-workorder/{jenis_workorder_id}/detail-form',          [DetailFormController::class, 'store']);
        Route::get('jenis-workorder/{jenis_workorder_id}/detail-form/{id}',      [DetailFormController::class, 'show']);
        Route::put('jenis-workorder/{jenis_workorder_id}/detail-form/{id}',      [DetailFormController::class, 'update']);
        Route::delete('jenis-workorder/{jenis_workorder_id}/detail-form/{id}',   [DetailFormController::class, 'destroy']);


        Route::get('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form',           [DetailFormController::class, 'index']);
        Route::post('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form',          [DetailFormController::class, 'store']);
        Route::get('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form/{id}',      [DetailFormController::class, 'show']);
        Route::put('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form/{id}',      [DetailFormController::class, 'update']);
        Route::delete('jenis-workorder/{jenis_workorder_id}/form-workorder/{form_workorder_id}/detail-form/{id}',   [DetailFormController::class, 'destroy']);

        Route::get('kpi', [KpiController::class, 'index']);

        Route::apiResource('material', MaterialController::class);
        Route::patch('material/{kode_material}/pakai', [MaterialController::class, 'update']);
        Route::put('material/{kode_material}/edit',    [MaterialController::class, 'edit']);
    });
});

// =========================================================
// [S] Health Check (unversioned — di luar prefix v1)
// =========================================================
Route::get('ping', function () {
    return response()->json([
        'message'   => 'API Laravel Connected!',
        'version'   => 'v1',
        'timestamp' => now()->toISOString(),
    ]);
});
