<?php

use App\Http\Controllers\Api\CloudflareProxyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// In api.php
Route::prefix('cloudflare')->group(function () {
    Route::get('/users', [CloudflareProxyController::class, 'getUsers']);
    Route::get('/users/{id}', [CloudflareProxyController::class, 'getUser']);
    Route::post('/users', [CloudflareProxyController::class, 'createUser']);
    Route::put('/users/{id}', [CloudflareProxyController::class, 'updateUser']);
    Route::delete('/users/{id}', [CloudflareProxyController::class, 'deleteUser']);
});