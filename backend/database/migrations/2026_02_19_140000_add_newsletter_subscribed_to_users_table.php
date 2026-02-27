<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('newsletter_subscribed')
                ->default(false)
                ->index('users_newsletter_subscribed_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_newsletter_subscribed_index');
            $table->dropColumn('newsletter_subscribed');
        });
    }
};
