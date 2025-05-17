<?php

use App\Http\Controllers\GamingController;
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

Route::post('sendOtp', [GamingController::class, 'sendOtp']);
Route::post('register', [GamingController::class, 'register']);

Route::middleware(['jwt.auth'])->group(function () {
    Route::post('postScore', [GamingController::class, 'saveScore']);
    Route::get('overallScore', [GamingController::class, 'overallScore']);
    Route::get('weeklyScore', [GamingController::class, 'weeklyScore']);
});
