<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventWidgetController;
use App\Http\Controllers\Api\EventEmailAlertController;
use App\Http\Controllers\Api\EventCalendarController;
use App\Http\Controllers\Api\EventReminderController;
use App\Http\Controllers\Api\EventInviteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\PollController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\GifSearchController;
use App\Http\Controllers\Api\BlogPostController;
use App\Http\Controllers\Api\BlogTagController;
use App\Http\Controllers\Api\BlogPostCommentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NasaIotdController;

use App\Http\Controllers\Api\Admin\EventCandidateController;
use App\Http\Controllers\Api\Admin\EventCandidateReviewController;
use App\Http\Controllers\Api\Admin\EventCandidateMetaController;
use App\Http\Controllers\Api\Admin\CrawlRunController;
use App\Http\Controllers\Api\Admin\EventSourceController;
use App\Http\Controllers\Api\Admin\AdminBlogPostController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\AdminEventController;
use App\Http\Controllers\Api\Admin\EventTranslationController;
use App\Http\Controllers\Api\Admin\ManualEventController;
use App\Http\Controllers\Api\Admin\ReportQueueController;
use App\Http\Controllers\Api\Admin\AstroBotController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\AdminPostController;
use App\Http\Controllers\Api\Admin\ModerationQueueController;
use App\Http\Controllers\Api\Admin\TranslationHealthController;
use App\Http\Controllers\Api\Admin\AdminNewsletterController;
use App\Http\Controllers\Api\Admin\ContestController as AdminContestController;
use App\Http\Controllers\Api\Admin\AdminStatsController;
use App\Http\Controllers\Api\Admin\SidebarSectionController as AdminSidebarSectionController;
use App\Http\Controllers\Api\SidebarSectionController;
use App\Http\Controllers\Api\Admin\SidebarConfigController as AdminSidebarConfigController;
use App\Http\Controllers\Api\Admin\SidebarCustomComponentController as AdminSidebarCustomComponentController;
use App\Http\Controllers\Api\SidebarConfigController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\HashtagController;
use App\Http\Controllers\Api\ContestController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ObserveSummaryController;
use App\Http\Controllers\Api\ObserveDiagnosticsController;
use App\Http\Controllers\Api\ObservingSkySummaryController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\MarkYourCalendarPopupController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\MeLocationController;
use App\Http\Controllers\Api\MeDataExportController;
use App\Http\Controllers\CsrfTestController;
use App\Http\Controllers\Api\Admin\FeaturedEventController;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'status' => 'ok',
        'app' => config('app.name'),
        'env' => app()->environment(),
        'time' => now()->toIso8601String(),
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
| AUTH - Sanctum SPA (cookies)
|--------------------------------------------------------------------------
*/
Route::middleware('web')->prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
    Route::get('/username-available', [AuthController::class, 'usernameAvailable'])->middleware('throttle:auth-username-available');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware(['auth:sanctum']);

    Route::get('/me', [AuthController::class, 'me'])
        ->middleware(['auth:sanctum', 'active']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware(['auth:sanctum', 'active', 'throttle:6,1']);
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
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
Route::get('/events/years', [EventController::class, 'years']);
Route::get('/events/next', [EventController::class, 'next']);
Route::get('/events/widget/upcoming', [EventWidgetController::class, 'upcoming']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/events/{event}/ics', [EventCalendarController::class, 'show']);
Route::post('/events/{event}/notify-email', [EventEmailAlertController::class, 'store']);
Route::get('/invites/public/{token}', [EventInviteController::class, 'publicShow']);

Route::get('/nasa/iotd', [NasaIotdController::class, 'show']);
Route::get('/observe/summary', ObserveSummaryController::class);
Route::get('/observe/diagnostics', ObserveDiagnosticsController::class);
Route::get('/observing/sky-summary', ObservingSkySummaryController::class);
Route::get('/meta/interests', [MetaController::class, 'interests']);
Route::get('/meta/locations', [MetaController::class, 'locations']);

/*
|--------------------------------------------------------------------------
| Sidebar Sections (Public)
|--------------------------------------------------------------------------
*/
Route::get('/sidebar-sections', [SidebarSectionController::class, 'index']);
Route::get('/sidebar-config', [SidebarConfigController::class, 'index']);

/*
|--------------------------------------------------------------------------
| Posts (public feed + detail)
|--------------------------------------------------------------------------
*/
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::post('/posts/{post}/view', [PostController::class, 'view']);
Route::get('/polls/{poll}', [PollController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Feed endpoints
|--------------------------------------------------------------------------
*/
Route::get('/feed', [FeedController::class, 'index']);
Route::get('/feed/astrobot', [FeedController::class, 'astrobot']);

// Tag suggestions for autocomplete
Route::get('/tags/suggest', [TagController::class, 'suggest']);
Route::get('/integrations/gifs/search', GifSearchController::class)->middleware('throttle:gif-search');

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
Route::middleware('throttle:60,1')->prefix('search')->group(function () {
    Route::get('/users', [SearchController::class, 'users']);
    Route::get('/posts', [SearchController::class, 'posts']);
    Route::get('/suggest', [SearchController::class, 'suggest']);
});
/*
|--------------------------------------------------------------------------
| Hashtags (Public)
|--------------------------------------------------------------------------
*/
Route::get('/hashtags', [HashtagController::class, 'index']);
Route::get('/hashtags/{name}/posts', [HashtagController::class, 'posts']);
Route::get('/trending', [HashtagController::class, 'trending']);
Route::get('/contests/active', [ContestController::class, 'active']);
Route::get('/contests/{contest}/participants', [ContestController::class, 'participants']);

/*
|--------------------------------------------------------------------------
| Recommendations (Authenticated)
|--------------------------------------------------------------------------
*/
Route::get('/recommendations/users', [RecommendationController::class, 'users'])
    ->middleware(['auth:sanctum', 'active', 'verified']);
Route::get('/recommendations/posts', [RecommendationController::class, 'posts']);

/*
|--------------------------------------------------------------------------
| Blog posts (public)
|--------------------------------------------------------------------------
*/
Route::get('/blog-posts', [BlogPostController::class, 'index']);
Route::get('/articles/widget', [BlogPostController::class, 'widget']);
Route::get('/blog-posts/{slug}/related', [BlogPostController::class, 'related']);
Route::get('/blog-posts/{slug}', [BlogPostController::class, 'show']);
Route::get('/blog-posts/{slug}/comments', [BlogPostCommentController::class, 'index']);
Route::post('/blog-posts/{slug}/comments', [BlogPostCommentController::class, 'store'])
    ->middleware(['auth:sanctum', 'active', 'verified']);
Route::delete('/blog-posts/{slug}/comments/{comment}', [BlogPostCommentController::class, 'destroy'])
    ->middleware(['auth:sanctum', 'active', 'verified']);

Route::post('/reports', [ReportController::class, 'store'])
    ->middleware(['auth:sanctum', 'active', 'verified']);
Route::get('/blog-tags', [BlogTagController::class, 'index']);

/*
|--------------------------------------------------------------------------
| FAVORITES (auth required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->prefix('favorites')->group(function () {
    Route::middleware(['active', 'verified'])->group(function () {
        Route::get('/',           [FavoriteController::class, 'index']);
        Route::post('/',          [FavoriteController::class, 'store']);
        Route::delete('/{event}', [FavoriteController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| ADMIN - Event Candidates (Crawling + Review)
|--------------------------------------------------------------------------
| Funguje:
|  - SPA (cookies)
|  - API token (Bearer ...)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active', 'verified', 'admin'])
    ->prefix('admin')
    ->group(function () {

        // Dashboard
        Route::get('/dashboard', DashboardController::class);
        Route::get('/stats', [AdminStatsController::class, 'index']);
        Route::get('/stats/export', [AdminStatsController::class, 'export']);

        // Candidates (list + detail)
        Route::get('/event-candidates',                  [EventCandidateController::class, 'index']);
        Route::get('/event-candidates/{eventCandidate}', [EventCandidateController::class, 'show']);

        // Meta for filters
        Route::get('/event-candidates-meta', EventCandidateMetaController::class);

        // Review process
        Route::post('/event-candidates/{candidate}/approve', [EventCandidateReviewController::class, 'approve']);
        Route::post('/event-candidates/{candidate}/reject',  [EventCandidateReviewController::class, 'reject']);

        // Crawl runs
        Route::get('/crawl-runs',            [CrawlRunController::class, 'index']);
        Route::get('/crawl-runs/{crawlRun}', [CrawlRunController::class, 'show']);
        Route::get('/event-sources', [EventSourceController::class, 'index']);
        Route::patch('/event-sources/{eventSource}', [EventSourceController::class, 'update']);
        Route::post('/event-sources/run', [EventSourceController::class, 'run']);
        Route::get('/translation-health', TranslationHealthController::class);
        Route::get('/contests', [AdminContestController::class, 'index']);
        Route::post('/contests', [AdminContestController::class, 'store']);
        Route::patch('/contests/{contest}', [AdminContestController::class, 'update']);
        Route::post('/contests/{contest}/select-winner', [AdminContestController::class, 'selectWinner']);

        /*
        |----------------------------------------------------------------------
        | Sidebar Sections (Admin)
        |----------------------------------------------------------------------
        */
        Route::get('/sidebar-sections', [AdminSidebarSectionController::class, 'index']);
        Route::put('/sidebar-sections', [AdminSidebarSectionController::class, 'update']);
        Route::get('/sidebar-config', [AdminSidebarConfigController::class, 'index']);
        Route::put('/sidebar-config', [AdminSidebarConfigController::class, 'update']);
        Route::get('/sidebar/custom-components', [AdminSidebarCustomComponentController::class, 'index']);
        Route::post('/sidebar/custom-components', [AdminSidebarCustomComponentController::class, 'store']);
        Route::get('/sidebar/custom-components/{component}', [AdminSidebarCustomComponentController::class, 'show']);
        Route::put('/sidebar/custom-components/{component}', [AdminSidebarCustomComponentController::class, 'update']);
        Route::patch('/sidebar/custom-components/{component}', [AdminSidebarCustomComponentController::class, 'update']);
        Route::delete('/sidebar/custom-components/{component}', [AdminSidebarCustomComponentController::class, 'destroy']);

        // Mark your calendar popup (admin)
        Route::get('/featured-events', [FeaturedEventController::class, 'index']);
        Route::post('/featured-events', [FeaturedEventController::class, 'store']);
        Route::patch('/featured-events/{featuredEvent}', [FeaturedEventController::class, 'update']);
        Route::delete('/featured-events/{featuredEvent}', [FeaturedEventController::class, 'destroy']);
        Route::post('/featured-events/apply-fallback', [FeaturedEventController::class, 'applyFallback']);
        Route::post('/featured-events/force-popup', [FeaturedEventController::class, 'forcePopup']);
        Route::patch('/featured-events/popup-settings', [FeaturedEventController::class, 'updatePopupSettings']);

        // Newsletter (admin)
        Route::get('/newsletter/preview', [AdminNewsletterController::class, 'preview']);
        Route::post('/newsletter/preview', [AdminNewsletterController::class, 'sendPreview'])->middleware('throttle:newsletter-preview');
        Route::post('/newsletter/feature-events', [AdminNewsletterController::class, 'featureEvents']);
        Route::post('/newsletter/send', [AdminNewsletterController::class, 'send'])->middleware('throttle:newsletter-send');
        Route::get('/newsletter/runs', [AdminNewsletterController::class, 'runs']);

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
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::get('/users/{id}/reports', [AdminUserController::class, 'reports']);
        Route::patch('/users/{user}/ban', [AdminUserController::class, 'ban']);
        Route::post('/users/{user}/ban', [AdminUserController::class, 'ban']);
        Route::patch('/users/{user}/unban', [AdminUserController::class, 'unban']);
        Route::post('/users/{user}/unban', [AdminUserController::class, 'unban']);
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
        Route::post('/events/retranslate', [EventTranslationController::class, 'backfill']);

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
        | Moderation Queue (admin)
        |----------------------------------------------------------------------
        */
        Route::get('/moderation', [ModerationQueueController::class, 'index']);
        Route::get('/moderation/{post}', [ModerationQueueController::class, 'show']);
        Route::post('/moderation/{post}/action', [ModerationQueueController::class, 'action']);

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
            Route::get('/nasa/status', [AstroBotController::class, 'nasaStatus']);
            Route::post('/nasa/sync-now', [AstroBotController::class, 'syncNow'])->middleware('throttle:astrobot-sync');
            Route::get('/items', [AstroBotController::class, 'items']);
            Route::put('/items/{item}', [AstroBotController::class, 'update']);
            Route::post('/items/{item}/publish', [AstroBotController::class, 'publish']);
            Route::post('/items/{item}/reject', [AstroBotController::class, 'reject']);
            Route::post('/items/{item}/discard', [AstroBotController::class, 'discard']);
            Route::get('/posts', [AstroBotController::class, 'posts']);
            Route::delete('/posts/{post}', [AstroBotController::class, 'deletePost']);
            Route::post('/rss-items/{item}/retranslate', [AstroBotController::class, 'retranslate']);
            Route::post('/rss-items/retranslate-pending', [AstroBotController::class, 'retranslatePending']);
            Route::post('/sync', [AstroBotController::class, 'syncRss'])->middleware('throttle:astrobot-sync');
            Route::post('/rss/refresh', [AstroBotController::class, 'refreshRss'])->middleware('throttle:astrobot-sync');
        });
    });

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['active', 'verified'])->group(function () {

        // Profile
        Route::patch('/profile',          [ProfileController::class, 'update']);
        Route::post('/profile/media',     [ProfileController::class, 'uploadMedia']);
        Route::patch('/profile/password', [ProfileController::class, 'changePassword']);
        Route::delete('/profile',         [ProfileController::class, 'destroy']);

        /*
        |----------------------------------------------------------------------
        | Posts (create - auth)
        |----------------------------------------------------------------------
        */
        Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:post-create');
        Route::post('/posts/{post}/reply', [PostController::class, 'reply'])->middleware('throttle:post-create');
        Route::post('/posts/{post}/like', [PostController::class, 'like']);
        Route::delete('/posts/{post}/like', [PostController::class, 'unlike']);
        Route::post('/posts/{post}/bookmark', [BookmarkController::class, 'store'])->middleware('throttle:60,1');
        Route::delete('/posts/{post}/bookmark', [BookmarkController::class, 'destroy'])->middleware('throttle:60,1');
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        Route::post('/polls/{poll}/vote', [PollController::class, 'vote']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
        Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
        if (app()->environment('local') && config('app.debug')) {
            Route::post('/notifications/dev-test', [NotificationController::class, 'devTest']);
        }
        Route::middleware('throttle:30,1')->group(function () {
            Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
            Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        });

        // Event reminders
        Route::post('/events/{event}/reminders', [EventReminderController::class, 'store']);
        Route::post('/events/{event}/invites', [EventInviteController::class, 'store']);
        Route::get('/me/invites', [EventInviteController::class, 'index']);
        Route::post('/invites/{invite}/accept', [EventInviteController::class, 'accept']);
        Route::post('/invites/{invite}/decline', [EventInviteController::class, 'decline']);
        Route::get('/me/bookmarks', [BookmarkController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/me/reminders', [EventReminderController::class, 'index']);
        Route::get('/me/preferences', [\App\Http\Controllers\Api\UserPreferenceController::class, 'show']);
        Route::put('/me/preferences', [\App\Http\Controllers\Api\UserPreferenceController::class, 'update']);
        Route::put('/me/location', [MeLocationController::class, 'update']);
        Route::get('/me/export', MeDataExportController::class)->middleware('throttle:me-export');
        Route::patch('/me/newsletter', [NewsletterSubscriptionController::class, 'update']);
        Route::delete('/reminders/{reminder}', [EventReminderController::class, 'destroy']);

        // Mark your calendar popup
        Route::get('/popup/mark-your-calendar', [MarkYourCalendarPopupController::class, 'show']);
        Route::post('/popup/mark-your-calendar/seen', [MarkYourCalendarPopupController::class, 'seen']);
    });
});
