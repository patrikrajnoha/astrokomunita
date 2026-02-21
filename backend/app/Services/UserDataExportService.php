<?php

namespace App\Services;

use App\Http\Resources\Export\InviteExportResource;
use App\Http\Resources\Export\PostExportResource;
use App\Http\Resources\Export\UserExportResource;
use App\Models\EventInvite;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Collection;

class UserDataExportService
{
    public const EXPORT_VERSION = '1.0';

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
                'created_at',
                'updated_at',
                'latitude',
                'longitude',
                'timezone',
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
                'attachment_mime',
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
        $invites = EventInvite::query()
            ->where(function ($query) use ($freshUser, $normalizedEmail): void {
                $query->where('invitee_user_id', $freshUser->id);

                if ($normalizedEmail !== '') {
                    $query->orWhereRaw('LOWER(invitee_email) = ?', [$normalizedEmail]);
                }
            })
            ->with([
                'event:id,title,start_at',
            ])
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
            ])
            ->orderBy('created_at')
            ->get();

        return [
            'export_version' => self::EXPORT_VERSION,
            'exported_at' => now()->utc()->toIso8601String(),
            'user' => (new UserExportResource($freshUser))->resolve(),
            'newsletter' => [
                'subscribed' => (bool) $freshUser->newsletter_subscribed,
                'subscribed_at' => null,
                'frequency' => null,
            ],
            'posts' => $this->transformCollection($posts, PostExportResource::class),
            'invites' => $this->transformCollection($invites, InviteExportResource::class),
            'data_summary' => [
                'posts_count' => $posts->count(),
                'invites_count' => $invites->count(),
                'invites_scope' => 'received',
            ],
        ];
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
}
