<?php

use App\Enums\RegionScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('region_scope', 16)
                ->default(RegionScope::Global->value)
                ->after('type')
                ->index('events_region_scope_index');

            $table->index('type', 'events_type_index');
            $table->index(['type', 'region_scope'], 'events_type_region_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_type_region_scope_index');
            $table->dropIndex('events_type_index');
            $table->dropIndex('events_region_scope_index');
            $table->dropColumn('region_scope');
        });
    }
};
