<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventWidgetController;
use App\Http\Controllers\Api\EventEmailAlertController;
use App\Http\Controllers\Api\EventCalendarController;
use App\Http\Controllers\Api\FeaturedEventsCalendarController;
use App\Http\Controllers\Api\EventReminderController;
use App\Http\Controllers\Api\EventInviteController;
use App\Http\Controllers\Api\EventViewingForecastController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\UserNotificationPreferenceController;
use App\Http\Controllers\Api\EventFollowController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\AccountEmailController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ProfilePinnedPostController;
use App\Http\Controllers\Api\ObservationController;
use App\Http\Controllers\Api\MediaDownloadController;
use App\Http\Controllers\Api\MediaViewController;
use App\Http\Controllers\Api\PublicMediaFileController;
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
use App\Http\Controllers\Api\Admin\EventTranslationHealthController;
use App\Http\Controllers\Api\Admin\ManualEventController;
use App\Http\Controllers\Api\Admin\ReportQueueController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\AdminPostController;
use App\Http\Controllers\Api\Admin\ModerationQueueController;
use App\Http\Controllers\Api\Admin\ModerationHubController;
use App\Http\Controllers\Api\Admin\TranslationHealthController;
use App\Http\Controllers\Api\Admin\AdminNewsletterController;
use App\Http\Controllers\Api\Admin\AdminAiController;
use App\Http\Controllers\Api\Admin\AuthSettingsController;
use App\Http\Controllers\Api\Admin\ContestController as AdminContestController;
use App\Http\Controllers\Api\Admin\AdminStatsController;
use App\Http\Controllers\Api\Admin\AdminBotController;
use App\Http\Controllers\Api\Admin\PerformanceMetricsController;
use App\Http\Controllers\Api\SidebarSectionController;
use App\Http\Controllers\Api\SidebarConfigController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\HashtagController;
use App\Http\Controllers\Api\ContestController;
use App\Http\Controllers\Api\RecommendationController;
use App\Http\Controllers\Api\ObserveSummaryController;
use App\Http\Controllers\Api\ObserveDiagnosticsController;
use App\Http\Controllers\Api\ObservingSkySummaryController;
use App\Http\Controllers\Api\SkyController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\MarkYourCalendarPopupController;
use App\Http\Controllers\Api\NewsletterSubscriptionController;
use App\Http\Controllers\Api\MeLocationController;
use App\Http\Controllers\Api\MeDataExportController;
use App\Http\Controllers\Api\MeDataExportJobController;
use App\Http\Controllers\Api\MeDataExportJobDownloadController;
use App\Http\Controllers\Api\MeDataExportSummaryController;
use App\Http\Controllers\Api\MeActivityController;
use App\Http\Controllers\Api\Admin\FeaturedEventController;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    $payload = [
        'ok' => true,
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ];

    if ((bool) config('security.health.expose_diagnostics', false)) {
        $payload['env'] = app()->environment();
    }

    return response()->json($payload);
});

Route::get('/_health', function () {
    $payload = [
        'ok' => true,
        'status' => 'ok',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ];

    if ((bool) config('security.health.expose_diagnostics', false)) {
        $gitSha = trim((string) (env('APP_GIT_SHA') ?: env('GIT_SHA') ?: ''));
        if ($gitSha === '') {
            $gitSha = trim((string) @shell_exec('git rev-parse --short HEAD'));
        }

        $buildId = trim((string) (env('APP_BUILD_ID') ?: env('RELEASE_SHA') ?: ''));

        $payload['env'] = app()->environment();
        $payload['git_sha'] = $gitSha !== '' ? $gitSha : null;
        $payload['build_id'] = $buildId !== '' ? $buildId : null;
    }

    return response()->json($payload);
});


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
    Route::post('/password/forgot', [PasswordResetController::class, 'sendCode'])->middleware('throttle:auth-password-reset-send');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:auth-password-reset-confirm');
    Route::get('/username-available', [AuthController::class, 'usernameAvailable'])->middleware('throttle:auth-username-available');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware(['auth:sanctum']);

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->middleware(['throttle:6,1']);
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['throttle:6,1'])
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
Route::get('/events/lookup', [EventController::class, 'lookup']);
Route::get('/events/widget/upcoming', [EventWidgetController::class, 'upcoming']);
Route::get('/events/{id}', [EventController::class, 'show']);
Route::get('/events/{id}/viewing-forecast', [EventViewingForecastController::class, 'show']);
Route::get('/events/{event}/ics', [EventCalendarController::class, 'show']);
Route::get('/events/{event}/calendar.ics', [EventCalendarController::class, 'showCalendarIcs']);
Route::get('/featured-events/{month}/calendar.ics', [FeaturedEventsCalendarController::class, 'showBundle'])
    ->where('month', '^\\d{4}-\\d{2}$');
Route::post('/events/{event}/notify-email', [EventEmailAlertController::class, 'store']);
Route::get('/invites/public/{token}', [EventInviteController::class, 'publicShow']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/events/{id}/follow-state', [EventFollowController::class, 'state'])->middleware('throttle:60,1');
    Route::post('/events/{id}/follow', [EventFollowController::class, 'store'])->middleware('throttle:60,1');
    Route::delete('/events/{id}/follow', [EventFollowController::class, 'destroy'])->middleware('throttle:60,1');
    Route::patch('/events/{id}/plan', [EventFollowController::class, 'plan'])->middleware('throttle:60,1');
    Route::get('/me/followed-events', [EventFollowController::class, 'index'])->middleware('throttle:60,1');
});

Route::get('/nasa/iotd', [NasaIotdController::class, 'show']);
Route::get('/observe/summary', ObserveSummaryController::class);
Route::get('/observe/diagnostics', ObserveDiagnosticsController::class);
Route::get('/observing/sky-summary', ObservingSkySummaryController::class);
Route::prefix('sky')->group(function () {
    Route::get('/weather', [SkyController::class, 'weather'])->middleware('sky.throttle:cheap');
    Route::get('/astronomy', [SkyController::class, 'astronomy'])->middleware('sky.throttle:cheap');
    Route::get('/moon-phases', [SkyController::class, 'moonPhases'])->middleware('sky.throttle:cheap');
    Route::get('/moon-overview', [SkyController::class, 'moonOverview'])->middleware('sky.throttle:cheap');
    Route::get('/moon-events', [SkyController::class, 'moonEvents'])->middleware('sky.throttle:cheap');
    Route::get('/visible-planets', [SkyController::class, 'visiblePlanets'])->middleware('sky.throttle:expensive');
    Route::get('/ephemeris', [SkyController::class, 'ephemeris'])->middleware('sky.throttle:expensive');
    Route::get('/iss-preview', [SkyController::class, 'issPreview'])->middleware('sky.throttle:expensive');
    Route::get('/light-pollution', [SkyController::class, 'lightPollution'])->middleware('sky.throttle:expensive');
});
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
Route::get('/observations', [ObservationController::class, 'index']);
Route::get('/observations/{observation}', [ObservationController::class, 'show']);
Route::get('/media/{media}', MediaViewController::class)->name('media.view');
Route::get('/media/{media}/download', MediaDownloadController::class)->name('media.download');
Route::get('/media/file/{path}', PublicMediaFileController::class)->where('path', '.*')->name('media.file');
Route::get('/polls/{poll}', [PollController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Feed endpoints
|--------------------------------------------------------------------------
*/
Route::get('/feed', [FeedController::class, 'index']);
Route::get('/astro-feed', [FeedController::class, 'astro']);
Route::get('/feed/astro', [FeedController::class, 'astro']);

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
    Route::get('/global', [SearchController::class, 'global']);
    Route::get('/events', [SearchController::class, 'events']);
    Route::get('/articles', [SearchController::class, 'articles']);
    Route::get('/hashtags', [SearchController::class, 'hashtags']);
    Route::get('/keywords', [SearchController::class, 'keywords']);
    Route::get('/discovery', [SearchController::class, 'discovery']);
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
Route::get('/recommendations/posts', [RecommendationController::class, 'posts'])
    ->middleware(['auth:sanctum', 'active', 'verified']);

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
    ->middleware(['auth:sanctum', 'active', 'verified', 'throttle:report-submissions']);
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
        Route::get('/performance-metrics', [PerformanceMetricsController::class, 'index']);
        Route::post('/performance-metrics/run', [PerformanceMetricsController::class, 'run']);
        Route::get('/auth-settings', [AuthSettingsController::class, 'show']);
        Route::patch('/auth-settings', [AuthSettingsController::class, 'update']);
        Route::get('/settings/email-verification', [AuthSettingsController::class, 'show']);
        Route::put('/settings/email-verification', [AuthSettingsController::class, 'update']);

        // Candidates (list + detail)
        Route::get('/event-candidates',                  [EventCandidateController::class, 'index']);
        Route::get('/event-candidates/duplicates/preview', [EventCandidateController::class, 'duplicatesPreview']);
        Route::post('/event-candidates/duplicates/merge', [EventCandidateController::class, 'mergeDuplicates']);
        Route::get('/event-candidates/{eventCandidate}', [EventCandidateController::class, 'show']);

        // Meta for filters
        Route::get('/event-candidates-meta', EventCandidateMetaController::class);

        // Review process
        Route::post('/event-candidates/{candidate}/approve', [EventCandidateReviewController::class, 'approve']);
        Route::post('/event-candidates/approve-batch', [EventCandidateReviewController::class, 'approveBatch']);
        Route::post('/event-candidates/{candidate}/reject',  [EventCandidateReviewController::class, 'reject']);
        Route::post('/event-candidates/{candidate}/retranslate', [EventCandidateReviewController::class, 'retranslate']);
        Route::post('/event-candidates/retranslate-batch', [EventCandidateReviewController::class, 'retranslateBatch']);
        Route::patch('/event-candidates/{candidate}/translation', [EventCandidateReviewController::class, 'updateTranslation']);

        // Crawl runs
        Route::get('/crawl-runs',            [CrawlRunController::class, 'index']);
        Route::get('/crawl-runs/{crawlRun}', [CrawlRunController::class, 'show']);
        Route::get('/event-sources', [EventSourceController::class, 'index']);
        Route::patch('/event-sources/{eventSource}', [EventSourceController::class, 'update']);
        Route::post('/event-sources/run', [EventSourceController::class, 'run']);
        Route::post('/event-sources/purge', [EventSourceController::class, 'purge']);
        Route::get('/event-sources/translation-artifacts/report', [EventSourceController::class, 'translationArtifactsReport']);
        Route::post('/event-sources/translation-artifacts/repair', [EventSourceController::class, 'translationArtifactsRepair']);
        Route::get('/translation-health', TranslationHealthController::class);
        Route::get('/event-translation-health', EventTranslationHealthController::class);
        Route::get('/contests/hashtags-preview', [AdminContestController::class, 'hashtagsPreview']);

        // Mark your calendar popup (admin)
        Route::get('/featured-events', [FeaturedEventController::class, 'index']);
        Route::post('/featured-events', [FeaturedEventController::class, 'store']);
        Route::patch('/featured-events/{featuredEvent}', [FeaturedEventController::class, 'update']);
        Route::delete('/featured-events/{featuredEvent}', [FeaturedEventController::class, 'destroy']);
        Route::post('/featured-events/apply-fallback', [FeaturedEventController::class, 'applyFallback']);
        Route::post('/featured-events/force-popup', [FeaturedEventController::class, 'forcePopup']);
        Route::patch('/featured-events/popup-settings', [FeaturedEventController::class, 'updatePopupSettings']);

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
        Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole']);
        Route::patch('/users/{user}/profile', [AdminUserController::class, 'updateProfile']);
        Route::post('/users/{user}/avatar', [AdminUserController::class, 'uploadAvatar']);
        Route::patch('/users/{user}/avatar', [AdminUserController::class, 'uploadAvatar']);
        Route::delete('/users/{user}/avatar', [AdminUserController::class, 'removeAvatar']);
        Route::patch('/users/{user}/avatar/preferences', [AdminUserController::class, 'updateAvatarPreferences']);
        Route::post('/users/{user}/cover', [AdminUserController::class, 'uploadCover']);
        Route::patch('/users/{user}/cover', [AdminUserController::class, 'uploadCover']);
        Route::delete('/users/{user}/cover', [AdminUserController::class, 'removeCover']);
        Route::post('/users/{id}/deactivate', [AdminUserController::class, 'deactivate']);
        Route::post('/users/{id}/reactivate', [AdminUserController::class, 'reactivate']);
        Route::post('/users/{id}/reset-profile', [AdminUserController::class, 'resetProfile']);

        /*
        |----------------------------------------------------------------------
        | Events (admin)
        |----------------------------------------------------------------------
        */
        Route::post('/events/{event}/ai/generate-description', [AdminAiController::class, 'generateEventDescription'])
            ->middleware('throttle:admin-ai');
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
        Route::post('/manual-events/publish-batch', [ManualEventController::class, 'publishBatch']);

        /*
        |----------------------------------------------------------------------
        | Moderation Hub (admin)
        |----------------------------------------------------------------------
        */
        Route::get('/moderation/overview', [ModerationHubController::class, 'overview']);
        Route::get('/moderation/review-feed', [ModerationHubController::class, 'reviewFeed']);

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
        Route::get('/moderation/health', [ModerationQueueController::class, 'health']);
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

        Route::prefix('bots')->group(function () {
            Route::get('/overview', [AdminBotController::class, 'overview']);
            Route::get('/sources', [AdminBotController::class, 'sources']);
            Route::patch('/sources/{sourceId}', [AdminBotController::class, 'updateSource']);
            Route::post('/sources/{sourceId}/reset-health', [AdminBotController::class, 'resetSourceHealth']);
            Route::post('/sources/{sourceId}/clear-cooldown', [AdminBotController::class, 'clearSourceCooldown']);
            Route::post('/sources/{sourceId}/revive', [AdminBotController::class, 'reviveSource']);
            Route::get('/runs', [AdminBotController::class, 'runs']);
            Route::get('/activity', [AdminBotController::class, 'activity']);
            Route::get('/schedules', [AdminBotController::class, 'schedules']);
            Route::post('/schedules', [AdminBotController::class, 'createSchedule']);
            Route::patch('/schedules/{scheduleId}', [AdminBotController::class, 'updateSchedule']);
            Route::delete('/schedules/{scheduleId}', [AdminBotController::class, 'deleteSchedule']);
            Route::get('/items', [AdminBotController::class, 'items']);
            Route::post('/run/{sourceKey}', [AdminBotController::class, 'run']);
            Route::post('/translation/test', [AdminBotController::class, 'translationTest']);
            Route::get('/translation/health', [AdminBotController::class, 'translationHealth']);
            Route::post('/translation/simulate-outage', [AdminBotController::class, 'updateTranslationSimulateOutage']);
            Route::post('/translation/retry/{sourceKey}', [AdminBotController::class, 'retryTranslation']);
            Route::post('/translation/backfill/{sourceKey}', [AdminBotController::class, 'backfillTranslation']);
            Route::post('/items/{botItemId}/publish', [AdminBotController::class, 'publishItem']);
            Route::delete('/items/{botItemId}/post', [AdminBotController::class, 'deleteItemPost']);
            Route::delete('/posts', [AdminBotController::class, 'deleteAllPosts']);
            Route::get('/post-retention', [AdminBotController::class, 'postRetention']);
            Route::patch('/post-retention', [AdminBotController::class, 'updatePostRetention']);
            Route::post('/post-retention/cleanup', [AdminBotController::class, 'runPostRetentionCleanup']);
            Route::post('/runs/{runId}/publish', [AdminBotController::class, 'publishRun']);
        });
    });

Route::middleware(['auth:sanctum', 'active', 'verified', 'admin.content'])
    ->prefix('admin')
    ->group(function () {
        Route::get('/events', [AdminEventController::class, 'index']);

        // Newsletter (admin + editor)
        Route::get('/newsletter/preview', [AdminNewsletterController::class, 'preview']);
        Route::post('/newsletter/preview', [AdminNewsletterController::class, 'sendPreview'])->middleware('throttle:newsletter-preview');
        Route::post('/newsletter/ai/prime-insights', [AdminAiController::class, 'primeNewsletterInsights'])
            ->middleware(['admin', 'throttle:admin-ai']);
        Route::post('/newsletter/ai/draft-copy', [AdminAiController::class, 'draftNewsletterCopy'])
            ->middleware(['admin', 'throttle:admin-ai']);
        Route::post('/newsletter/feature-events', [AdminNewsletterController::class, 'featureEvents']);
        Route::post('/newsletter/send', [AdminNewsletterController::class, 'send'])->middleware('throttle:newsletter-send');
        Route::get('/newsletter/runs', [AdminNewsletterController::class, 'runs']);
        Route::get('/ai/config', [AdminAiController::class, 'config'])
            ->middleware(['admin', 'throttle:admin-ai']);

        /*
        |--------------------------------------------------------------------------
        | Blog posts (admin + editor)
        |--------------------------------------------------------------------------
        */
        Route::get('/blog-posts', [AdminBlogPostController::class, 'index']);
        Route::get('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'show']);
        Route::post('/blog-posts', [AdminBlogPostController::class, 'store']);
        Route::put('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'update']);
        Route::patch('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'update']);
        Route::post('/blog-posts/{blogPost}/ai/suggest-tags', [AdminBlogPostController::class, 'suggestTags'])
            ->middleware(['admin', 'throttle:admin-ai']);
        Route::delete('/blog-posts/{blogPost}', [AdminBlogPostController::class, 'destroy']);
    });

/*
|--------------------------------------------------------------------------
| AUTHENTICATED USER ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/account/email', [AccountEmailController::class, 'show']);
    Route::post('/account/email/verification/send', [AccountEmailController::class, 'sendVerificationCode']);
    Route::post('/account/email/verification/confirm', [AccountEmailController::class, 'confirmVerificationCode']);
    Route::post('/account/email/change/request', [AccountEmailController::class, 'requestEmailChange']);
    Route::post('/account/email/change/confirm-current', [AccountEmailController::class, 'confirmCurrentEmailChange']);
    Route::post('/account/email/change/confirm-new', [AccountEmailController::class, 'confirmNewEmailChange']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['active', 'verified'])->group(function () {

        // Profile
        Route::patch('/profile',          [ProfileController::class, 'update']);
        Route::post('/profile/media',     [ProfileController::class, 'uploadMedia']);
        Route::post('/me/avatar-image',   [ProfileController::class, 'uploadAvatarImage']);
        Route::delete('/me/avatar-image', [ProfileController::class, 'removeAvatarImage']);
        Route::patch('/me/avatar',        [ProfileController::class, 'updateAvatar']);
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
        Route::patch('/posts/{post}', [PostController::class, 'update']);
        Route::delete('/posts/{post}', [PostController::class, 'destroy']);
        Route::patch('/profile/posts/{post}/pin', [ProfilePinnedPostController::class, 'pin']);
        Route::patch('/profile/posts/{post}/unpin', [ProfilePinnedPostController::class, 'unpin']);
        Route::post('/observations', [ObservationController::class, 'store'])->middleware('throttle:post-create');
        Route::patch('/observations/{observation}', [ObservationController::class, 'update']);
        Route::put('/observations/{observation}', [ObservationController::class, 'update']);
        Route::delete('/observations/{observation}', [ObservationController::class, 'destroy']);
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
        Route::get('/me/activity', MeActivityController::class);
        Route::get('/me/preferences', [\App\Http\Controllers\Api\UserPreferenceController::class, 'show']);
        Route::post('/me/preferences', [\App\Http\Controllers\Api\UserPreferenceController::class, 'update']);
        Route::put('/me/preferences', [\App\Http\Controllers\Api\UserPreferenceController::class, 'update']);
        Route::patch('/me/location', [MeLocationController::class, 'update']);
        Route::put('/me/location', [MeLocationController::class, 'update']);
        Route::get('/me/location/auto', [MeLocationController::class, 'auto']);
        Route::get('/me/notifications/preferences', [UserNotificationPreferenceController::class, 'show']);
        Route::post('/me/notifications/preferences', [UserNotificationPreferenceController::class, 'update']);
        Route::post('/me/export/jobs', [MeDataExportJobController::class, 'store'])->middleware('throttle:me-export-jobs');
        Route::get('/me/export/jobs/{job}', [MeDataExportJobController::class, 'show'])->middleware('throttle:30,1');
        Route::get('/me/export/jobs/{job}/download', MeDataExportJobDownloadController::class)
            ->name('me.export.jobs.download')
            ->middleware('throttle:30,1');
        Route::get('/me/export/summary', MeDataExportSummaryController::class)->middleware('throttle:30,1');
        Route::get('/me/export', MeDataExportController::class)->middleware('throttle:me-export');
        Route::patch('/me/newsletter', [NewsletterSubscriptionController::class, 'update']);
        Route::delete('/reminders/{reminder}', [EventReminderController::class, 'destroy']);

        // Mark your calendar popup
        Route::get('/popup/mark-your-calendar', [MarkYourCalendarPopupController::class, 'show']);
        Route::post('/popup/mark-your-calendar/seen', [MarkYourCalendarPopupController::class, 'seen']);
    });
});
