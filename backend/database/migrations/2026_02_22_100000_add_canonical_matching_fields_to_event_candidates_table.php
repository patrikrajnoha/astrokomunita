<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            $table->decimal('confidence_score', 4, 2)->nullable()->after('stable_key');
            $table->string('canonical_key', 191)->nullable()->after('confidence_score');
            $table->json('matched_sources')->nullable()->after('canonical_key');
            $table->index('canonical_key', 'event_candidates_canonical_key_idx');
        });
    }

    public function down(): void
    {
        Schema::table('event_candidates', function (Blueprint $table) {
            $table->dropIndex('event_candidates_canonical_key_idx');
            $table->dropColumn([
                'confidence_score',
                'canonical_key',
                'matched_sources',
            ]);
        });
    }
};
