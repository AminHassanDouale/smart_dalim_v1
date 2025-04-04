<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\CloudflareProxyController;

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

// Proxy routes to Cloudflare Worker
Route::prefix('cloudflare')->group(function () {
    // Users endpoints
    Route::get('/users', function (Request $request) {
        $workerUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
        $response = Http::get("{$workerUrl}/api/users");
        return $response->json();
    });

    Route::get('/users/{id}', function (Request $request, $id) {
        $workerUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
        $response = Http::get("{$workerUrl}/api/users/{$id}");
        return $response->json();
    });

    Route::post('/users', function (Request $request) {
        $workerUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
        $response = Http::post("{$workerUrl}/api/users", $request->all());
        return $response->json();
    });

    Route::put('/users/{id}', function (Request $request, $id) {
        $workerUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
        $response = Http::put("{$workerUrl}/api/users/{$id}", $request->all());
        return $response->json();
    });

    Route::delete('/users/{id}', function (Request $request, $id) {
        $workerUrl = env('CLOUDFLARE_WORKER_URL', 'https://cold-hall-95bc.hassanamin191.workers.dev');
        $response = Http::delete("{$workerUrl}/api/users/{$id}");
        return $response->json();
    });
});
