<?php

namespace App\Services;

use App\Http\Resources\Export\BookmarkExportResource;
use App\Http\Resources\Export\FollowedEventExportResource;
use App\Http\Resources\Export\InviteExportResource;
use App\Http\Resources\Export\NotificationPreferenceExportResource;
use App\Http\Resources\Export\PostExportResource;
use App\Http\Resources\Export\ReminderExportResource;
use App\Http\Resources\Export\UserExportResource;
use App\Models\EventInvite;
use App\Models\EventReminder;
use App\Models\Post;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Storage\MediaStorageService;
use App\Support\EventFollowTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserDataExportService
{
    public const EXPORT_VERSION = '2.0';

    public function __construct(
        private readonly UserActivityService $activityService,
        private readonly MediaStorageService $mediaStorage,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $freshUser = User::query()
            ->select([
                'id',
                'name',
                'username',
                'email',
                'email_verified_at',
                'date_of_birth',
                'bio',
                'avatar_path',
                'cover_path',
                'created_at',
                'updated_at',
                'latitude',
                'longitude',
                'timezone',
                'location_label',
                'location_source',
                'newsletter_subscribed',
            ])
            ->with('eventPreference')
            ->findOrFail($user->id);

        $posts = Post::query()
            ->where('user_id', $freshUser->id)
            ->select([
                'id',
                'user_id',
                'parent_id',
                'root_id',
                'content',
                'attachment_path',
                'attachment_original_path',
                'attachment_web_path',
                'attachment_mime',
                'attachment_original_mime',
                'attachment_web_mime',
                'attachment_size',
                'attachment_web_size',
                'attachment_web_width',
                'attachment_web_height',
                'source_name',
                'source_url',
                'source_published_at',
                'is_hidden',
                'hidden_at',
                'moderation_status',
                'created_at',
                'updated_at',
            ])
            ->orderBy('created_at')
            ->get();

        $normalizedEmail = strtolower(trim((string) $freshUser->email));
        $invitesReceived = $this->queryReceivedInvites($freshUser, $normalizedEmail)
            ->orderBy('created_at')
            ->get();
        $invitesSent = $this->querySentInvites($freshUser)
            ->orderBy('created_at')
            ->get();

        $reminders = EventReminder::query()
            ->where('user_id', $freshUser->id)
            ->with('event:id,title,start_at')
            ->select([
                'id',
                'user_id',
                'event_id',
                'minutes_before',
                'remind_at',
                'status',
                'sent_at',
                'created_at',
                'updated_at',
            ])
            ->orderBy('created_at')
            ->get();

        $followedEvents = $this->queryFollowedEvents($freshUser->id);
        $bookmarks = $this->queryBookmarks($freshUser->id);

        $notificationPreferences = UserNotificationPreference::query()
            ->firstOrNew(
                ['user_id' => $freshUser->id],
                UserNotificationPreference::defaults()
            );

        $activity = $this->activityService->getActivity($freshUser);
        $summary = $this->summary($freshUser);

        $payload = [
            'export_version' => self::EXPORT_VERSION,
            'schema_version' => self::EXPORT_VERSION,
            'exported_at' => now()->utc()->toIso8601String(),
            'generated_by' => [
                'app' => (string) config('app.name', 'app'),
                'environment' => (string) app()->environment(),
            ],
            'user' => (new UserExportResource($freshUser))->resolve(),
            'newsletter' => [
                'subscribed' => (bool) $freshUser->newsletter_subscribed,
                'subscribed_at' => null,
                'frequency' => null,
            ],
            'notification_preferences' => (new NotificationPreferenceExportResource($notificationPreferences))->resolve(),
            'activity' => $activity,
            'posts' => $this->transformCollection($posts, PostExportResource::class),
            // Legacy key preserved for backward compatibility.
            'invites' => $this->transformCollection($invitesReceived, InviteExportResource::class),
            'invites_received' => $this->transformCollection($invitesReceived, InviteExportResource::class),
            'invites_sent' => $this->transformCollection($invitesSent, InviteExportResource::class),
            'reminders' => $this->transformCollection($reminders, ReminderExportResource::class),
            'followed_events' => $this->transformCollection($followedEvents, FollowedEventExportResource::class),
            'bookmarks' => $this->transformCollection($bookmarks, BookmarkExportResource::class),
            'sections' => $summary['section_counts'],
            'data_summary' => [
                'posts_count' => $summary['counts']['posts_count'],
                'invites_count' => $summary['counts']['invites_received_count'],
                'invites_scope' => 'received',
                'invites_received_count' => $summary['counts']['invites_received_count'],
                'invites_sent_count' => $summary['counts']['invites_sent_count'],
                'reminders_count' => $summary['counts']['reminders_count'],
                'followed_events_count' => $summary['counts']['followed_events_count'],
                'bookmarks_count' => $summary['counts']['bookmarks_count'],
                'attachments_count' => $summary['counts']['attachments_count'],
                'estimated_bytes' => $summary['estimated_bytes'],
            ],
        ];

        return $this->attachChecksum($payload);
    }

    /**
     * @return array{
     *   generated_at: string,
     *   schema_version: string,
     *   estimated_bytes: int,
     *   counts: array{
     *     posts_count: int,
     *     invites_received_count: int,
     *     invites_sent_count: int,
     *     reminders_count: int,
     *     followed_events_count: int,
     *     bookmarks_count: int,
     *     attachments_count: int
     *   },
     *   section_counts: array<string, int>,
     *   sections: array<int, string>
     * }
     */
    public function summary(User $user): array
    {
        $freshUser = User::query()
            ->select(['id', 'email'])
            ->findOrFail($user->id);

        $normalizedEmail = strtolower(trim((string) $freshUser->email));

        $postsCount = (int) Post::query()
            ->where('user_id', $freshUser->id)
            ->count();

        $postBodyBytes = (int) Post::query()
            ->where('user_id', $freshUser->id)
            ->selectRaw('COALESCE(SUM(LENGTH(content)), 0) as total_length')
            ->value('total_length');

        $invitesReceivedCount = (int) $this->queryReceivedInvites($freshUser, $normalizedEmail)->count();
        $invitesSentCount = (int) $this->querySentInvites($freshUser)->count();

        $remindersCount = (int) EventReminder::query()
            ->where('user_id', $freshUser->id)
            ->count();

        $table = EventFollowTable::resolve();
        $followedEventsCount = (int) DB::table($table)
            ->where('user_id', $freshUser->id)
            ->count();

        $bookmarksCount = (int) DB::table('post_user_bookmarks')
            ->where('user_id', $freshUser->id)
            ->count();

        $attachmentsCount = (int) Post::query()
            ->where('user_id', $freshUser->id)
            ->where(function (Builder $query): void {
                $query->whereNotNull('attachment_original_path')
                    ->orWhereNotNull('attachment_web_path')
                    ->orWhereNotNull('attachment_path');
            })
            ->count();

        $attachmentsEstimatedBytes = (int) Post::query()
            ->where('user_id', $freshUser->id)
            ->selectRaw('COALESCE(SUM(COALESCE(attachment_original_size, attachment_web_size, attachment_size, 0)), 0) as total_size')
            ->value('total_size');

        $sectionCounts = [
            'user' => 1,
            'newsletter' => 1,
            'notification_preferences' => 1,
            'activity' => 1,
            'posts' => $postsCount,
            'invites_received' => $invitesReceivedCount,
            'invites_sent' => $invitesSentCount,
            'reminders' => $remindersCount,
            'followed_events' => $followedEventsCount,
            'bookmarks' => $bookmarksCount,
        ];

        $estimatedBytes = max(
            2048,
            3200
            + $postBodyBytes
            + $attachmentsEstimatedBytes
            + ($postsCount * 420)
            + (($invitesReceivedCount + $invitesSentCount) * 240)
            + ($remindersCount * 180)
            + ($followedEventsCount * 220)
            + ($bookmarksCount * 120)
        );

        return [
            'generated_at' => now()->utc()->toIso8601String(),
            'schema_version' => self::EXPORT_VERSION,
            'estimated_bytes' => $estimatedBytes,
            'counts' => [
                'posts_count' => $postsCount,
                'invites_received_count' => $invitesReceivedCount,
                'invites_sent_count' => $invitesSentCount,
                'reminders_count' => $remindersCount,
                'followed_events_count' => $followedEventsCount,
                'bookmarks_count' => $bookmarksCount,
                'attachments_count' => $attachmentsCount,
            ],
            'section_counts' => $sectionCounts,
            'sections' => array_keys($sectionCounts),
        ];
    }

    /**
     * @return array<int, array{
     *   post_id:int,
     *   source:string,
     *   disk:string,
     *   path:string,
     *   zip_path:string,
     *   mime:string|null,
     *   size_bytes:int|null
     * }>
     */
    public function collectAttachmentSources(User $user): array
    {
        $posts = Post::query()
            ->where('user_id', $user->id)
            ->select([
                'id',
                'attachment_path',
                'attachment_original_path',
                'attachment_web_path',
                'attachment_mime',
                'attachment_original_mime',
                'attachment_web_mime',
                'attachment_size',
                'attachment_original_size',
                'attachment_web_size',
                'attachment_original_name',
            ])
            ->orderBy('id')
            ->get();

        $publicDisk = $this->mediaStorage->publicDiskName();
        $privateDisk = $this->mediaStorage->privateDiskName();
        $sources = [];

        foreach ($posts as $post) {
            $candidate = $this->resolveAttachmentCandidateForZip($post, $publicDisk, $privateDisk);
            if ($candidate === null) {
                continue;
            }

            $sources[] = $candidate;
        }

        return $sources;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function withChecksum(array $payload): array
    {
        return $this->attachChecksum($payload);
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function transformCollection(Collection $items, string $resourceClass): array
    {
        return $items
            ->map(static fn ($item): array => (new $resourceClass($item))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return Builder<EventInvite>
     */
    private function queryReceivedInvites(User $user, string $normalizedEmail): Builder
    {
        return EventInvite::query()
            ->where(function ($query) use ($user, $normalizedEmail): void {
                $query->where('invitee_user_id', $user->id);

                if ($normalizedEmail !== '') {
                    $query->orWhereRaw('LOWER(invitee_email) = ?', [$normalizedEmail]);
                }
            })
            ->with(['event:id,title,start_at'])
            ->select([
                'id',
                'event_id',
                'inviter_user_id',
                'invitee_user_id',
                'invitee_email',
                'attendee_name',
                'message',
                'status',
                'responded_at',
                'created_at',
            ]);
    }

    /**
     * @return Builder<EventInvite>
     */
    private function querySentInvites(User $user): Builder
    {
        return EventInvite::query()
            ->where('inviter_user_id', $user->id)
            ->with(['event:id,title,start_at'])
            ->select([
                'id',
                'event_id',
                'inviter_user_id',
                'invitee_user_id',
                'invitee_email',
                'attendee_name',
                'message',
                'status',
                'responded_at',
                'created_at',
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function queryFollowedEvents(int $userId): Collection
    {
        $table = EventFollowTable::resolve();
        $supportsPlan = EventFollowTable::supportsPersonalPlanColumns($table);
        $query = DB::table($table . ' as f')
            ->join('events as e', 'e.id', '=', 'f.event_id')
            ->where('f.user_id', $userId)
            ->orderBy('f.created_at')
            ->select([
                'e.id as event_id',
                'e.title as event_title',
                'e.type as event_type',
                'e.start_at as event_start_at',
                'e.end_at as event_end_at',
                'e.source_name as event_source_name',
                'e.source_uid as event_source_uid',
                'f.created_at as followed_at',
            ]);

        if ($supportsPlan) {
            $query->addSelect([
                'f.personal_note as personal_note',
                'f.reminder_at as reminder_at',
                'f.planned_time as planned_time',
                'f.planned_location_label as planned_location_label',
            ]);
        } else {
            $query->addSelect([
                DB::raw('NULL as personal_note'),
                DB::raw('NULL as reminder_at'),
                DB::raw('NULL as planned_time'),
                DB::raw('NULL as planned_location_label'),
            ]);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function queryBookmarks(int $userId): Collection
    {
        return DB::table('post_user_bookmarks as b')
            ->leftJoin('posts as p', 'p.id', '=', 'b.post_id')
            ->where('b.user_id', $userId)
            ->orderBy('b.created_at')
            ->select([
                'b.post_id as post_id',
                'b.created_at as bookmarked_at',
                'p.content as post_content',
                'p.created_at as post_created_at',
                'p.is_hidden as post_is_hidden',
                'p.hidden_at as post_hidden_at',
                'p.moderation_status as post_moderation_status',
            ])
            ->get();
    }

    /**
     * @return array{
     *   post_id:int,
     *   source:string,
     *   disk:string,
     *   path:string,
     *   zip_path:string,
     *   mime:string|null,
     *   size_bytes:int|null
     * }|null
     */
    private function resolveAttachmentCandidateForZip(object $post, string $publicDisk, string $privateDisk): ?array
    {
        $candidates = [
            [
                'source' => 'original',
                'disk' => $privateDisk,
                'path' => $post->attachment_original_path ?? null,
                'mime' => $post->attachment_original_mime ?? null,
                'size_bytes' => isset($post->attachment_original_size) ? (int) $post->attachment_original_size : null,
            ],
            [
                'source' => 'web',
                'disk' => $publicDisk,
                'path' => $post->attachment_web_path ?? null,
                'mime' => $post->attachment_web_mime ?? null,
                'size_bytes' => isset($post->attachment_web_size) ? (int) $post->attachment_web_size : null,
            ],
            [
                'source' => 'attachment',
                'disk' => $publicDisk,
                'path' => $post->attachment_path ?? null,
                'mime' => $post->attachment_mime ?? null,
                'size_bytes' => isset($post->attachment_size) ? (int) $post->attachment_size : null,
            ],
        ];

        foreach ($candidates as $candidate) {
            $path = trim((string) ($candidate['path'] ?? ''));
            if ($path === '') {
                continue;
            }

            $disk = (string) ($candidate['disk'] ?? '');
            if ($disk === '' || !$this->diskFileExists($disk, $path)) {
                continue;
            }

            $mime = isset($candidate['mime']) ? trim((string) $candidate['mime']) : '';
            $extension = strtolower(trim((string) pathinfo($path, PATHINFO_EXTENSION)));
            if ($extension === '') {
                $extension = $this->extensionForMime($mime);
            }
            if ($extension === '') {
                $extension = 'bin';
            }

            $rawName = trim((string) ($post->attachment_original_name ?? ''));
            if ($rawName === '') {
                $rawName = (string) pathinfo($path, PATHINFO_FILENAME);
            }
            $safeBase = Str::slug(pathinfo($rawName, PATHINFO_FILENAME));
            if ($safeBase === '') {
                $safeBase = 'attachment-' . (int) $post->id;
            }

            $zipPath = sprintf('attachments/post-%d/%s.%s', (int) $post->id, $safeBase, $extension);

            return [
                'post_id' => (int) $post->id,
                'source' => (string) ($candidate['source'] ?? 'attachment'),
                'disk' => $disk,
                'path' => $path,
                'zip_path' => $zipPath,
                'mime' => $mime !== '' ? $mime : null,
                'size_bytes' => isset($candidate['size_bytes']) && $candidate['size_bytes'] !== null
                    ? (int) $candidate['size_bytes']
                    : null,
            ];
        }

        return null;
    }

    private function diskFileExists(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match (strtolower(trim($mime))) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attachChecksum(array $payload): array
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            $payload['checksum_sha256'] = null;
            return $payload;
        }

        $payload['checksum_sha256'] = hash('sha256', $encoded);

        return $payload;
    }
}
