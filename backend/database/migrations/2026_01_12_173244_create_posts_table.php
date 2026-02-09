<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->foreignId('root_id')->nullable()->constrained('posts')->cascadeOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->text('content');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_mime')->nullable();
            $table->string('attachment_original_name')->nullable();
            $table->unsignedBigInteger('attachment_size')->nullable();
            $table->string('source_name', 50)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('source_uid', 191)->nullable();
            $table->timestamp('source_published_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->text('hidden_reason')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['parent_id', 'is_hidden'], 'posts_parent_hidden_index');
            $table->index('source_published_at', 'posts_source_published_at_index');
            $table->index('pinned_at');
            $table->index('is_hidden');
            $table->index(['expires_at', 'user_id']);
            $table->unique(['source_name', 'source_uid'], 'posts_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
