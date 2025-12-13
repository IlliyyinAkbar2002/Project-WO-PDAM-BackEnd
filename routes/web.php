<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Route untuk serve storage files (bypass symlink issue di Windows PHP built-in server)
Route::get('/storage/{path}', function ($path) {
    $filePath = storage_path('app/public/' . $path);
    
    if (!file_exists($filePath)) {
        abort(404);
    }
    
    return response()->file($filePath);
})->where('path', '.*');

// Route SPA Sanctum
Route::post('/login', [AuthController::class, 'spaLogin']);
Route::post('/logout', [AuthController::class, 'spaLogout']);
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Group route yang butuh login
Route::middleware(['auth:sanctum'])->group(function () {
    // Hanya admin
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            return response()->json([
                'message' => 'Welcome Admin',
                'user' => auth()->user()
            ]);
        });
    });

    // Hanya user biasa
    Route::middleware('role:user')->group(function () {
        Route::get('/user/dashboard', function () {
            return response()->json([
                'message' => 'Welcome User',
                'user' => auth()->user()
            ]);
        });
    });
});



// Route::get('/sanctum/csrf-cookie', function () {
//     return response()->noContent();
// });
