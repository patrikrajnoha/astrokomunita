<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            $table->string('stable_key', 191)->nullable()->after('external_id');
            $table->unique(['event_source_id', 'stable_key'], 'event_candidates_source_stable_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            $table->dropUnique('event_candidates_source_stable_key_unique');
            $table->dropColumn('stable_key');
        });
    }
};
