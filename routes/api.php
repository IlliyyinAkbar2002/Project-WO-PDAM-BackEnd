<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DetailFormController;
use App\Http\Controllers\FormController;
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
use App\Models\Workorder;
use App\Services\ProgressWorkorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);

// Open listing/test routes
// Route::get('kpi', [KpiController::class, 'index']);
// Route::get('user', [UserController::class, 'index']);
// Route::get('workorder', [WorkorderController::class, 'index']);
// Route::get('jenis-workorder', [JenisWorkorderController::class, 'index']);
// Route::get('master-location', [MasterLocationController::class, 'index']);
// Route::get('form', [FormController::class, 'index']);
// Route::get('detail-form', [DetailFormController::class, 'index']);
// Route::get('jenis-lokasi', [JenisLokasiController::class, 'index']);
// Route::get('workorder-action', [WorkorderActionController::class, 'index']);
// Route::get('progress-workorder', [ProgressWorkorderController::class, 'index']);
// Route::get('detail-progress', [DetailProgressController::class, 'index']);
// Route::get('lembur-spl', [LemburSplController::class, 'index']);

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->user(),
        ]);
    });

    // Example protected utility
    Route::post('/progress-workorder/manual-run', function () {
        $service = new ProgressWorkorderService();

        $workorders = Workorder::where('status_id', 7)->get();

        foreach ($workorders as $workorder) {
            $service->addWorkorderProgress($workorder->id);
        }

        return response()->json(['message' => 'Progress ditambahkan untuk semua workorder aktif']);
    });

    Route::get('kpi', [KpiController::class, 'index']);
    Route::get('user', [UserController::class, 'index']);
    Route::get('workorder', [WorkorderController::class, 'index']);
    Route::get('jenis-workorder', [JenisWorkorderController::class, 'index']);
    Route::get('master-location', [MasterLocationController::class, 'index']);
    Route::get('form', [FormController::class, 'index']);
    Route::get('detail-form', [DetailFormController::class, 'index']);
    Route::get('jenis-lokasi', [JenisLokasiController::class, 'index']);
    Route::get('workorder-action', [WorkorderActionController::class, 'index']);
    Route::get('progress-workorder', [ProgressWorkorderController::class, 'index']);
    Route::get('detail-progress', [DetailProgressController::class, 'index']);
    Route::get('lembur-spl', [LemburSplController::class, 'index']);

    // If you later want to protect the resources, move them here
});

Route::get('/ping', function () {
    return response()->json([
        'message' => 'API Laravel Connected!'
    ]);
});

