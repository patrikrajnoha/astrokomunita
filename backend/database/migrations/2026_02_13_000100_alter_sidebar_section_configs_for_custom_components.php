<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sidebar_section_configs', function (Blueprint $table) {
            $table->dropUnique('sidebar_section_configs_scope_section_key_unique');
            $table->string('kind', 32)->default('builtin')->after('scope');
            $table->foreignId('custom_component_id')
                ->nullable()
                ->after('section_key')
                ->constrained('sidebar_custom_components')
                ->nullOnDelete();
            $table->unique(['scope', 'kind', 'section_key', 'custom_component_id'], 'sidebar_scope_kind_section_custom_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sidebar_section_configs', function (Blueprint $table) {
            $table->dropUnique('sidebar_scope_kind_section_custom_unique');
            $table->dropConstrainedForeignId('custom_component_id');
            $table->dropColumn('kind');
            $table->unique(['scope', 'section_key']);
        });
    }
};

