<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\BlogTagController;
use App\Http\Controllers\Api\BlogPostCommentController;

use App\Http\Controllers\Api\Admin\EventCandidateController;
use App\Http\Controllers\Api\Admin\EventCandidateReviewController;
use App\Http\Controllers\Api\Admin\EventCandidateMetaController;
use App\Http\Controllers\Api\Admin\CrawlRunController;
use App\Http\Controllers\Api\Admin\AdminBlogPostController;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'app'    => config('app.name'),
        'env'    => app()->environment(),
    ]);
});

/*
|--------------------------------------------------------------------------
| DEBUG ROUTES (len local + debug)
|--------------------------------------------------------------------------
*/
if (app()->environment('local') && config('app.debug')) {

    // 🔍 SPA / cookie debug (web + session)
    Route::get('/debug/auth', function () {
        return response()->json([
            'user_via_request' => request()->user(),
            'user_via_auth'    => Auth::user(),
            'web_guard'        => Auth::guard('web')->check(),
            'sanctum_guard'    => Auth::guard('sanctum')->check(),
            'session_id'       => session()->getId(),
        ]);
    })->middleware('web');

    // 🔑 Bearer token debug
    Route::get('/debug/token', function () {
        $user = request()->user();

        return response()->json([
            'authenticated' => (bool) $user,
            'user_id'       => $user?->id,
            'email'         => $user?->email,
            'is_admin'      => $user?->is_admin,
            'token_name'    => $user?->currentAccessToken()?->name,
            'token_id'      => $user?->currentAccessToken()?->id,
        ]);
    })->middleware('auth:sanctum');
}

/*
|--------------------------------------------------------------------------
| AUTH – Sanctum SPA (cookies)
|--------------------------------------------------------------------------
*/
Route::middleware('web')->prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('auth:sanctum');
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER (default /user route)
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| ASTRONOMICAL EVENTS (PUBLIC MVP)
|--------------------------------------------------------------------------
*/
Route::get('/events',      [EventController::class, 'index']);
Route::get('/events/next', [EventController::class, 'next']);
Route::get('/events/{id}', [EventController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Posts (public feed + detail)
|--------------------------------------------------------------------------
*/
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Blog posts (public)
|--------------------------------------------------------------------------
*/
Route::get('/blog-posts', [BlogPostController::class, 'index']);
Route::get('/blog-posts/{slug}/related', [BlogPostController::class, 'related']);
Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show']);
Route::get('/blog-posts/{slug}/comments', [BlogPostCommentController::class, 'index']);
Route::post('/blog-posts/{slug}/comments', [BlogPostCommentController::class, 'store'])
    ->middleware('auth:sanctum');
Route::delete('/blog-posts/{slug}/comments/{comment}', [BlogPostCommentController::class, 'destroy'])
    ->middleware('auth:sanctum');
Route::get('/blog-tags', [BlogTagController::class, 'index']);

/*
|--------------------------------------------------------------------------
| FAVORITES (zatiaľ bez auth)
|--------------------------------------------------------------------------
*/
Route::prefix('favorites')->group(function () {
    Route::get('/',           [FavoriteController::class, 'index']);
    Route::post('/',          [FavoriteController::class, 'store']);
    Route::delete('/{event}', [FavoriteController::class, 'destroy']);
});

/*
|--------------------------------------------------------------------------
| ADMIN – Event Candidates (Crawling + Review)
|--------------------------------------------------------------------------
| Funguje:
|  - SPA (cookies)
|  - API token (Bearer ...)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {

        // 📋 Kandidáti (list + detail)
        Route::get('/event-candidates',                  [EventCandidateController::class, 'index']);
        Route::get('/event-candidates/{eventCandidate}', [EventCandidateController::class, 'show']);

        // 🧠 Meta pre filtre
        Route::get('/event-candidates-meta', EventCandidateMetaController::class);

        // ✅ Review proces
        Route::post('/event-candidates/{candidate}/approve', [EventCandidateReviewController::class, 'approve']);
        Route::post('/event-candidates/{candidate}/reject',  [EventCandidateReviewController::class, 'reject']);

        // 🕷 Crawl runs
        Route::get('/crawl-runs',            [CrawlRunController::class, 'index']);
        Route::get('/crawl-runs/{crawlRun}', [CrawlRunController::class, 'show']);

        /*
        |--------------------------------------------------------------------------
        | Blog posts (admin)
        |--------------------------------------------------------------------------
        */
        Route::get('/blog-posts', [AdminBlogPostController::class, 'index']);
        Route::get('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'show']);
        Route::post('/blog-posts', [AdminBlogPostController::class, 'store']);
        Route::put('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'update']);
        Route::patch('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'update']);
        Route::delete('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {

    // 👤 Profil
    Route::patch('/profile',          [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'changePassword']);

    /*
    |----------------------------------------------------------------------
    | Posts (create - auth)
    |----------------------------------------------------------------------
    */
    Route::post('/posts', [PostController::class, 'store']);
});
