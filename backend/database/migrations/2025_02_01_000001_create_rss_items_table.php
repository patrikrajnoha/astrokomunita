<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64); // e.g. 'nasa_news'
            $table->string('guid')->nullable(); // RSS guid if present
            $table->string('url'); // link to original article
            $table->string('dedupe_hash', 64)->unique(); // sha1 of normalized url or guid fallback
            $table->string('title');
            $table->text('summary')->nullable();
            $table->dateTime('published_at')->nullable(); // original pubDate from RSS
            $table->dateTime('fetched_at'); // when we fetched it
            $table->string('status', 32)->default('pending'); // pending|approved|scheduled|published|discarded|error
            $table->dateTime('scheduled_for')->nullable();
            $table->unsignedBigInteger('post_id')->nullable(); // FK to posts.id after publish
            $table->text('last_error')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['source', 'guid'], 'rss_items_source_guid_index');
            $table->index('url', 'rss_items_url_index');
            $table->index('status', 'rss_items_status_index');
            $table->index('scheduled_for', 'rss_items_scheduled_for_index');
            $table->index('post_id', 'rss_items_post_id_index');
            $table->index('fetched_at', 'rss_items_fetched_at_index');

            // Foreign key
            $table->foreign('post_id')->references('id')->on('posts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_items');
    }
};
