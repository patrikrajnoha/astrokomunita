<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('cover_image_path')->nullable()->after('content');
            $table->string('cover_image_mime')->nullable()->after('cover_image_path');
            $table->string('cover_image_original_name')->nullable()->after('cover_image_mime');
            $table->unsignedBigInteger('cover_image_size')->nullable()->after('cover_image_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn([
                'cover_image_path',
                'cover_image_mime',
                'cover_image_original_name',
                'cover_image_size',
            ]);
        });
    }
};
