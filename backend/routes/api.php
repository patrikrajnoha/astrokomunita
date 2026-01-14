<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\EventCandidateController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PostController;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app' => config('app.name'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Auth (Sanctum SPA)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
| Astronomické udalosti (MVP)
|--------------------------------------------------------------------------
*/
Route::get('/events', [EventController::class, 'index']);
Route::get('/events/next', [EventController::class, 'next']);
Route::get('/events/{id}', [EventController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Príspevky (Community) - Verejné
|--------------------------------------------------------------------------
*/
Route::get('/posts', [PostController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Favorites (Zatiaľ bez auth)
|--------------------------------------------------------------------------
*/
Route::prefix('favorites')->group(function () {
    Route::get('/', [FavoriteController::class, 'index']);
    Route::post('/', [FavoriteController::class, 'store']);
    Route::delete('/{event}', [FavoriteController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| Admin: Event candidates (crawling staging)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/event-candidates', [EventCandidateController::class, 'index']);
        Route::get('/event-candidates/{eventCandidate}', [EventCandidateController::class, 'show']);
    });

/*
|--------------------------------------------------------------------------
| Chránené trasy (Len pre prihlásených používateľov)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    
    // Profil používateľa
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'changePassword']);

    // Príspevky (Vytváranie)
    Route::post('/posts', [PostController::class, 'store']);
    
});