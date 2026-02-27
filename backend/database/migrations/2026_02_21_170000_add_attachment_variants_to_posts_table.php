<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('attachment_original_path')->nullable()->after('attachment_path');
            $table->string('attachment_web_path')->nullable()->after('attachment_original_path');
            $table->string('attachment_original_mime')->nullable()->after('attachment_mime');
            $table->string('attachment_web_mime')->nullable()->after('attachment_original_mime');
            $table->unsignedBigInteger('attachment_original_size')->nullable()->after('attachment_size');
            $table->unsignedBigInteger('attachment_web_size')->nullable()->after('attachment_original_size');
            $table->unsignedInteger('attachment_web_width')->nullable()->after('attachment_web_size');
            $table->unsignedInteger('attachment_web_height')->nullable()->after('attachment_web_width');
            $table->json('attachment_variants_json')->nullable()->after('attachment_web_height');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_original_path',
                'attachment_web_path',
                'attachment_original_mime',
                'attachment_web_mime',
                'attachment_original_size',
                'attachment_web_size',
                'attachment_web_width',
                'attachment_web_height',
                'attachment_variants_json',
            ]);
        });
    }
};
