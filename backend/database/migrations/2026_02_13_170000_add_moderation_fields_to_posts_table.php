<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('pending')->after('is_hidden');
            $table->json('moderation_summary')->nullable()->after('moderation_status');
            $table->timestamp('hidden_at')->nullable()->after('hidden_reason');

            $table->string('attachment_moderation_status', 20)->nullable()->after('attachment_size');
            $table->json('attachment_moderation_summary')->nullable()->after('attachment_moderation_status');
            $table->boolean('attachment_is_blurred')->default(false)->after('attachment_moderation_summary');
            $table->timestamp('attachment_hidden_at')->nullable()->after('attachment_is_blurred');

            $table->index('moderation_status');
            $table->index('hidden_at');
            $table->index('attachment_moderation_status');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['moderation_status']);
            $table->dropIndex(['hidden_at']);
            $table->dropIndex(['attachment_moderation_status']);

            $table->dropColumn([
                'moderation_status',
                'moderation_summary',
                'hidden_at',
                'attachment_moderation_status',
                'attachment_moderation_summary',
                'attachment_is_blurred',
                'attachment_hidden_at',
            ]);
        });
    }
};
