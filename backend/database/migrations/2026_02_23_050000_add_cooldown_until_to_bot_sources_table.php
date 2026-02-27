<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_sources', function (Blueprint $table) {
            $table->timestamp('cooldown_until')
                ->nullable()
                ->after('last_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('bot_sources', function (Blueprint $table) {
            $table->dropColumn('cooldown_until');
        });
    }
};
