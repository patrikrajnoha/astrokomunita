<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Roots: enforce depth=0, root_id=null
        Post::query()
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->whereNull('depth')->orWhereNotNull('root_id');
            })
            ->update([
                'depth' => 0,
                'root_id' => null,
            ]);

        // Replies: fill missing root_id/depth using parent chain (max depth 2)
        Post::query()
            ->whereNotNull('parent_id')
            ->where(function ($q) {
                $q->whereNull('depth')->orWhereNull('root_id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($posts) {
                foreach ($posts as $post) {
                    $parent = Post::query()
                        ->select(['id', 'parent_id', 'root_id', 'depth'])
                        ->find($post->parent_id);

                    if (!$parent) {
                        continue;
                    }

                    if ($parent->parent_id === null) {
                        $post->depth = 1;
                        $post->root_id = $parent->id;
                    } else {
                        $post->depth = 2;
                        $post->root_id = $parent->root_id ?? $parent->parent_id;
                    }

                    $post->save();
                }
            });
    }

    public function down(): void
    {
        // No rollback for data backfill
    }
};
