<?php

namespace App\Events;

use App\Models\Post;
use App\Services\PostPayloadService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        private readonly Post $post,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('posts'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PostUpdated';
    }

    public function broadcastWith(): array
    {
        $post = Post::query()
            ->with([
                'user:id,name,username,location,bio,is_admin,is_bot,avatar_path,avatar_mode,avatar_color,avatar_icon,avatar_seed',
                'tags:id,name',
                'hashtags:id,name',
                'poll.options',
            ])
            ->find($this->post->id);

        $serialized = app(PostPayloadService::class)->serializePost(
            $post ?? $this->post,
            null
        );

        return [
            'post' => $serialized,
        ];
    }
}
