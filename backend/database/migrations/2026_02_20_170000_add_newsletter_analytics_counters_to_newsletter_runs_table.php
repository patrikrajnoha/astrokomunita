<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_runs', function (Blueprint $table): void {
            $table->unsignedInteger('preview_count')->default(0)->after('sent_count');
            $table->unsignedInteger('unsubscribe_count')->default(0)->after('preview_count');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_runs', function (Blueprint $table): void {
            $table->dropColumn(['preview_count', 'unsubscribe_count']);
        });
    }
};

