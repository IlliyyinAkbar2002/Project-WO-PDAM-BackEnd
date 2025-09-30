<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DetailFormController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\JenisLokasiController;
use App\Http\Controllers\JenisWorkorderController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LemburSplController;
use App\Http\Controllers\LocationController;
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

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

    //Bagian Auth
Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

// Protected (pakai sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
    //batasan auth

Route::middleware('auth:sanctum')->group(function() {
    Route::post('/locations', [LocationController::class, 'store']);
    Route::get('/master-locations', [MasterLocationController::class, 'index']);
    Route::patch('/master-locations/{id}', [MasterLocationController::class, 'update']); // untuk admin edit
});

Route::middleware('auth:sanctum')->group(function () {
    // simpan lokasi user
    Route::post('locations', [LocationController::class, 'store']);
    // lokasi terbaru user
    Route::get('locations/latest', [LocationController::class, 'latest']);
    // semua lokasi user (admin)
    Route::get('locations/all', [LocationController::class, 'all']);
    // cek posisi user terhadap master location
    Route::get('locations/check', [LocationController::class, 'check']);
});


//Route::apiResource('form', FormController::class);
Route::apiResource('detail-form', DetailFormController::class);
Route::apiResource('jenis-workorder', JenisWorkorderController::class);
Route::apiResource('kpi', KpiController::class);

Route::apiResource('jenis-lokasi', JenisLokasiController::class);
Route::apiResource('workorder', WorkorderController::class);
Route::apiResource('workorder-action', WorkorderActionController::class);

Route::apiResource('progress-workorder', ProgressWorkorderController::class);
Route::apiResource('detail-progress', DetailProgressController::class);

Route::apiResource('lembur-spl', LemburSplController::class);
Route::apiResource('user', UserController::class);
Route::apiResource('master-location', MasterLocationController::class);

Route::post('/progress-workorder/manual-run', function () {
    $service = new ProgressWorkorderService();

    $workorders = Workorder::where('status_id', 7)->get();

    foreach ($workorders as $workorder) {
        $service->addWorkorderProgress($workorder->id);
    }

    return response()->json(['message' => 'Progress ditambahkan untuk semua workorder aktif']);
});

Route::get('/ping', function () {
    return response()->json([
        'message' => 'API Laravel Connected!'
    ]);
});

