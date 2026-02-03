<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('name');
            $table->index('slug');
        });

        // Backfill slug from name for existing records
        DB::table('tags')->get()->each(function ($tag) {
            $slug = Str::slug($tag->name);
            
            // Ensure uniqueness by adding suffix if needed
            $originalSlug = $slug;
            $counter = 1;
            while (DB::table('tags')->where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            
            DB::table('tags')
                ->where('id', $tag->id)
                ->update(['slug' => $slug]);
        });

        // Make slug column required after backfill
        Schema::table('tags', function (Blueprint $table) {
            $table->string('slug', 100)->nullable(false)->change();
            $table->unique('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['slug']);
            $table->dropColumn('slug');
        });
    }
};
