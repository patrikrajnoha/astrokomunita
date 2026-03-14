<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('blog_post_tag') || !Schema::hasTable('tags')) {
            return;
        }

        $this->dropTagForeignKeyIfPresent();

        $mapping = $this->ensureBlogTagsAreMirroredInTags();
        $this->remapPivotTagIds($mapping);
        $this->deletePivotRowsMissingTagReference('tags');

        $this->addTagForeignKey('tags');
    }

    public function down(): void
    {
        if (!Schema::hasTable('blog_post_tag') || !Schema::hasTable('blog_tags')) {
            return;
        }

        $this->dropTagForeignKeyIfPresent();

        $mapping = $this->ensurePivotTagsAreMirroredInBlogTags();
        $this->remapPivotTagIds($mapping);
        $this->deletePivotRowsMissingTagReference('blog_tags');

        $this->addTagForeignKey('blog_tags');
    }

    /**
     * @return array<int,int> oldTagId => newTagId
     */
    private function ensureBlogTagsAreMirroredInTags(): array
    {
        if (!Schema::hasTable('blog_tags')) {
            return [];
        }

        $mapping = [];
        $rows = DB::table('blog_tags')
            ->select(['id', 'name', 'slug', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $oldId = (int) ($row->id ?? 0);
            $name = trim((string) ($row->name ?? ''));
            $slug = trim((string) ($row->slug ?? ''));

            if ($oldId <= 0 || $name === '') {
                continue;
            }

            $existingId = DB::table('tags')
                ->where('slug', $slug)
                ->orWhere('name', $name)
                ->value('id');

            if ($existingId === null) {
                $existingId = DB::table('tags')->insertGetId([
                    'name' => $name,
                    'slug' => $slug !== '' ? $slug : Str::slug($name),
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            $mapping[$oldId] = (int) $existingId;
        }

        return $mapping;
    }

    /**
     * @return array<int,int> oldTagId => newTagId
     */
    private function ensurePivotTagsAreMirroredInBlogTags(): array
    {
        $referencedTagIds = DB::table('blog_post_tag')
            ->distinct()
            ->pluck('tag_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($referencedTagIds === []) {
            return [];
        }

        $mapping = [];
        $sourceTags = DB::table('tags')
            ->whereIn('id', $referencedTagIds)
            ->get(['id', 'name', 'slug', 'created_at', 'updated_at']);

        foreach ($sourceTags as $tag) {
            $oldId = (int) ($tag->id ?? 0);
            $name = trim((string) ($tag->name ?? ''));
            $slug = trim((string) ($tag->slug ?? ''));

            if ($oldId <= 0 || $name === '') {
                continue;
            }

            $existingId = DB::table('blog_tags')
                ->where('slug', $slug)
                ->orWhere('name', $name)
                ->value('id');

            if ($existingId === null) {
                $existingId = DB::table('blog_tags')->insertGetId([
                    'name' => $name,
                    'slug' => $slug !== '' ? $slug : Str::slug($name),
                    'created_at' => $tag->created_at ?? now(),
                    'updated_at' => $tag->updated_at ?? now(),
                ]);
            }

            $mapping[$oldId] = (int) $existingId;
        }

        return $mapping;
    }

    /**
     * @param array<int,int> $mapping
     */
    private function remapPivotTagIds(array $mapping): void
    {
        if ($mapping === []) {
            return;
        }

        $rows = DB::table('blog_post_tag')
            ->select(['id', 'blog_post_id', 'tag_id'])
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $pivotId = (int) ($row->id ?? 0);
            $blogPostId = (int) ($row->blog_post_id ?? 0);
            $oldTagId = (int) ($row->tag_id ?? 0);
            $newTagId = (int) ($mapping[$oldTagId] ?? 0);

            if ($pivotId <= 0 || $blogPostId <= 0 || $newTagId <= 0 || $newTagId === $oldTagId) {
                continue;
            }

            $alreadyExists = DB::table('blog_post_tag')
                ->where('blog_post_id', $blogPostId)
                ->where('tag_id', $newTagId)
                ->where('id', '!=', $pivotId)
                ->exists();

            if ($alreadyExists) {
                DB::table('blog_post_tag')
                    ->where('id', $pivotId)
                    ->delete();
                continue;
            }

            DB::table('blog_post_tag')
                ->where('id', $pivotId)
                ->update(['tag_id' => $newTagId]);
        }
    }

    private function deletePivotRowsMissingTagReference(string $targetTagTable): void
    {
        DB::table('blog_post_tag')
            ->whereNotIn('tag_id', DB::table($targetTagTable)->select('id'))
            ->delete();
    }

    private function dropTagForeignKeyIfPresent(): void
    {
        try {
            Schema::table('blog_post_tag', function (Blueprint $table): void {
                $table->dropForeign(['tag_id']);
            });
        } catch (\Throwable) {
            // no-op when FK does not exist or current driver cannot drop it
        }
    }

    private function addTagForeignKey(string $tagTable): void
    {
        try {
            Schema::table('blog_post_tag', function (Blueprint $table) use ($tagTable): void {
                $table->foreign('tag_id')
                    ->references('id')
                    ->on($tagTable)
                    ->cascadeOnDelete();
            });
        } catch (\Throwable) {
            // no-op when FK already exists or current driver cannot create it
        }
    }
};
