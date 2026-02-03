<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventEmailAlertController;
use App\Http\Controllers\Api\EventCalendarController;
use App\Http\Controllers\Api\EventReminderController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\BlogTagController;
use App\Http\Controllers\Api\BlogPostCommentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NasaIotdController;

use App\Http\Controllers\Api\Admin\EventCandidateController;
use App\Http\Controllers\Api\Admin\EventCandidateReviewController;
use App\Http\Controllers\Api\Admin\EventCandidateMetaController;
use App\Http\Controllers\Api\Admin\CrawlRunController;
use App\Http\Controllers\Api\Admin\AdminBlogPostController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminEventController;
use App\Http\Controllers\Api\Admin\ManualEventController;
use App\Http\Controllers\Api\Admin\ReportQueueController;
use App\Http\Controllers\Api\Admin\AstroBotController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\AdminPostController;
use App\Http\Controllers\Api\Admin\SidebarSectionController as AdminSidebarSectionController;
use App\Http\Controllers\Api\SidebarSectionController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\HashtagController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\CsrfTestController;

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

Route::get('/csrf-test', [CsrfTestController::class, 'test']);

/*
|--------------------------------------------------------------------------
| DEBUG ROUTES (len local + debug)
|--------------------------------------------------------------------------
*/
if (app()->environment('local') && config('app.debug')) {

    // SPA / cookie debug (web + session)
    Route::get('/debug/auth', function () {
        return response()->json([
            'user_via_request' => request()->user(),
            'user_via_auth'    => Auth::user(),
            'web_guard'        => Auth::guard('web')->check(),
            'sanctum_guard'    => Auth::guard('sanctum')->check(),
            'session_id'       => session()->getId(),
        ]);
    })->middleware('web');

    // Bearer token debug
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

    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware(['auth:sanctum']);

    Route::get('/me', [AuthController::class, 'me'])
        ->middleware(['auth:sanctum', 'active']);
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER (default /user route)
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware(['auth:sanctum', 'active']);

/*
|--------------------------------------------------------------------------
| ASTRONOMICAL EVENTS (PUBLIC MVP)
|--------------------------------------------------------------------------
*/
Route::get('/events',      [EventController::class, 'index']);
Route::get('/events/next', [EventController::class, 'next']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/events/{event}/ics', [EventCalendarController::class, 'show']);
Route::post('/events/{event}/notify-email', [EventEmailAlertController::class, 'store']);

Route::get('/nasa/iotd', [NasaIotdController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Sidebar Sections (Public)
|--------------------------------------------------------------------------
*/
Route::get('/sidebar-sections', [SidebarSectionController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Posts (public feed + detail)
|--------------------------------------------------------------------------
*/
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Feed endpoints
|--------------------------------------------------------------------------
*/
Route::get('/feed', [FeedController::class, 'index']);
Route::get('/feed/astrobot', [FeedController::class, 'astrobot']);

// Tag suggestions for autocomplete
Route::get('/tags/suggest', [TagController::class, 'suggest']);

// Get posts by tag
Route::get('/tags/{tag}', [TagController::class, 'show']);

// Public user profiles
Route::get('/users/{username}', [App\Http\Controllers\Api\UserProfileController::class, 'show']);
Route::get('/users/{username}/posts', [App\Http\Controllers\Api\UserProfileController::class, 'posts']);

/*
|--------------------------------------------------------------------------
| Search & Discovery (Public)
|--------------------------------------------------------------------------
*/
Route::get('/search/users', [SearchController::class, 'users']);
Route::get('/search/posts', [SearchController::class, 'posts']);

/*
|--------------------------------------------------------------------------
| Hashtags (Public)
|--------------------------------------------------------------------------
*/
Route::get('/hashtags', [HashtagController::class, 'index']);
Route::get('/hashtags/{name}/posts', [HashtagController::class, 'posts']);
Route::get('/trending', [HashtagController::class, 'trending']);

/*
|--------------------------------------------------------------------------
| Recommendations (Authenticated)
|--------------------------------------------------------------------------
*/
Route::get('/recommendations/users', [RecommendationController::class, 'users'])
    ->middleware(['auth:sanctum', 'active']);
Route::get('/recommendations/posts', [RecommendationController::class, 'posts']);

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
    ->middleware(['auth:sanctum', 'active']);
Route::delete('/blog-posts/{slug}/comments/{comment}', [BlogPostCommentController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'active']);

Route::post('/reports', [ReportController::class, 'store'])
    ->middleware(['auth:sanctum', 'active']);
Route::get('/blog-tags', [BlogTagController::class, 'index']);

/*
|--------------------------------------------------------------------------
| FAVORITES (zatiaľ bez auth)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('favorites')->group(function () {
    Route::middleware('active')->group(function () {
        Route::get('/',           [FavoriteController::class, 'index']);
        Route::post('/',          [FavoriteController::class, 'store']);
        Route::delete('/{event}', [FavoriteController::class, 'destroy']);
    });
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
Route::middleware(['auth:sanctum', 'active', 'admin'])
    ->prefix('admin')
    ->group(function () {

        // � Dashboard
        Route::get('/dashboard', DashboardController::class);

        // �📋 Kandidáti (list + detail)
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
        |----------------------------------------------------------------------
        | Sidebar Sections (Admin)
        |----------------------------------------------------------------------
        */
        Route::get('/sidebar-sections', [AdminSidebarSectionController::class, 'index']);
        Route::put('/sidebar-sections', [AdminSidebarSectionController::class, 'update']);

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

        /*
        |----------------------------------------------------------------------
        | Users (admin)
        |----------------------------------------------------------------------
        */
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users/{id}/ban', [AdminUserController::class, 'ban']);
        Route::post('/users/{id}/unban', [AdminUserController::class, 'unban']);
        Route::post('/users/{id}/deactivate', [AdminUserController::class, 'deactivate']);
        Route::post('/users/{id}/reset-profile', [AdminUserController::class, 'resetProfile']);

        /*
        |----------------------------------------------------------------------
        | Events (admin)
        |----------------------------------------------------------------------
        */
        Route::get('/events', [AdminEventController::class, 'index']);
        Route::get('/events/{event}', [AdminEventController::class, 'show']);
        Route::post('/events', [AdminEventController::class, 'store']);
        Route::put('/events/{event}', [AdminEventController::class, 'update']);

        /*
        |----------------------------------------------------------------------
        | Manual event drafts (admin)
        |----------------------------------------------------------------------
        */
        Route::get('/manual-events', [ManualEventController::class, 'index']);
        Route::post('/manual-events', [ManualEventController::class, 'store']);
        Route::put('/manual-events/{manualEvent}', [ManualEventController::class, 'update']);
        Route::delete('/manual-events/{manualEvent}', [ManualEventController::class, 'destroy']);
        Route::post('/manual-events/{manualEvent}/publish', [ManualEventController::class, 'publish']);

        /*
        |----------------------------------------------------------------------
        | Reports (admin)
        |----------------------------------------------------------------------
        */
        Route::get('/reports', [ReportQueueController::class, 'index']);
        Route::post('/reports/{report}/dismiss', [ReportQueueController::class, 'dismiss']);
        Route::post('/reports/{report}/hide', [ReportQueueController::class, 'hide']);
        Route::post('/reports/{report}/delete', [ReportQueueController::class, 'delete']);
        Route::post('/reports/{report}/warn', [ReportQueueController::class, 'warn']);
        Route::post('/reports/{report}/ban', [ReportQueueController::class, 'ban']);

        /*
        |----------------------------------------------------------------------
        | Posts (admin)
        |----------------------------------------------------------------------
        */
        Route::patch('/posts/{post}/pin', [AdminPostController::class, 'pin']);
        Route::patch('/posts/{post}/unpin', [AdminPostController::class, 'unpin']);

        /*
        |--------------------------------------------------------------------------
        | AstroBot Admin (RSS pipeline)
        |--------------------------------------------------------------------------
        */
        Route::prefix('astrobot')->middleware('throttle:10,1')->group(function () {
            // RSS items management
            Route::get('/items', [AstroBotController::class, 'items']);
            Route::post('/fetch', [AstroBotController::class, 'fetch']);
            Route::put('/items/{id}', [AstroBotController::class, 'update']);
            Route::post('/items/{id}/approve', [AstroBotController::class, 'approve']);
            Route::post('/items/{id}/publish', [AstroBotController::class, 'publish']);
            Route::post('/items/{id}/schedule', [AstroBotController::class, 'schedule']);
            Route::post('/items/{id}/discard', [AstroBotController::class, 'discard']);

            // Bulk actions
            Route::post('/bulk', [AstroBotController::class, 'bulk']);

            // Published bot posts management
            Route::get('/posts', [AstroBotController::class, 'posts']);
            Route::delete('/posts/{id}', [AstroBotController::class, 'deletePost']);

            // Manual trigger for scheduled publishing
            Route::post('/publish-scheduled', [AstroBotController::class, 'publishScheduled']);
        });
    });

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware('active')->group(function () {

        // 👤 Profil
        Route::patch('/profile',          [ProfileController::class, 'update']);
        Route::post('/profile/media',     [ProfileController::class, 'uploadMedia']);
        Route::patch('/profile/password', [ProfileController::class, 'changePassword']);
        Route::delete('/profile',         [ProfileController::class, 'destroy']);

        /*
        |----------------------------------------------------------------------
        | Posts (create - auth)
        |----------------------------------------------------------------------
        */
        Route::post('/posts', [PostController::class, 'store']);
        Route::post('/posts/{post}/reply', [PostController::class, 'reply']);
        Route::post('/posts/{post}/like', [PostController::class, 'like']);
        Route::delete('/posts/{post}/like', [PostController::class, 'unlike']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        });

        // Event reminders
        Route::post('/events/{event}/reminders', [EventReminderController::class, 'store']);
        Route::get('/me/reminders', [EventReminderController::class, 'index']);
        Route::delete('/reminders/{reminder}', [EventReminderController::class, 'destroy']);
    });
});

