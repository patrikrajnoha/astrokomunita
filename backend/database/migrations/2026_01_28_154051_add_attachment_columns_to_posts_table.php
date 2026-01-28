<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('attachment_path')->nullable()->after('content');
            $table->string('attachment_mime')->nullable()->after('attachment_path');
            $table->string('attachment_original_name')->nullable()->after('attachment_mime');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_original_name');

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn([
                'attachment_path',
                'attachment_mime',
                'attachment_original_name',
                'attachment_size',
            ]);
        });
    }
};
