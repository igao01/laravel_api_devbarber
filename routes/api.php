<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BarberController;

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

Route::get('/ping', function() {
    return ['pong' => true, 'Hello' => 'World'];
});

//Route::get('/random', [BarberController::class, 'createRandom']);

Route::prefix('/auth')->group(function () {
    
    Route::get('/401', [AuthController::class, 'unauthorized'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/user', [UserController::class, 'create']);

});

Route::prefix('/user')->group(function() {

    Route::get('/', [UserController::class, 'read']);
    Route::put('/', [UserController::class, 'update']);
    Route::post('/avatar', [UserController::class, 'updateAvatar']);
    Route::get('/favorites', [UserController::class, 'getFavorites']);
    Route::post('/favorite', [UserController::class, 'toggleFavorite']);
    Route::get('/appointments', [UserController::class, 'getAppointments']);
});

Route::get('/barbers', [BarberController::class, 'list']);
Route::get('/barber/{id}', [BarberController::class, 'one']);
Route::post('/barber/{id}/appointment', [BarberController::class, 'setAppointment']);

Route::get('/search', [BarberController::class, 'search']);
