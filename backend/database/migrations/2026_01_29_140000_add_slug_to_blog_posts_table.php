<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('slug', 220)->nullable()->after('title');
            $table->unique('slug');
        });

        $posts = DB::table('blog_posts')
            ->select(['id', 'title', 'slug'])
            ->orderBy('id')
            ->get();

        $used = [];

        foreach ($posts as $post) {
            if (!empty($post->slug)) {
                $used[$post->slug] = true;
                continue;
            }

            $base = Str::slug($post->title ?: 'clanok');
            if ($base === '') {
                $base = 'clanok';
            }

            $slug = $base;
            $i = 2;
            while (isset($used[$slug]) || DB::table('blog_posts')->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i;
                $i++;
            }

            DB::table('blog_posts')
                ->where('id', $post->id)
                ->update(['slug' => $slug]);

            $used[$slug] = true;
        }
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
