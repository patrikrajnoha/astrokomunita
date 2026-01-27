<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('events', function (Blueprint $table) {
        $table->string('source_name')->nullable()->index();
        $table->string('source_uid')->nullable()->index();
        $table->string('source_hash')->nullable()->index();

        // voliteľné: ochrana proti duplicitnému publishu
        $table->unique(['source_name', 'source_uid']);
        // alebo: $table->unique('source_hash');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            //
        });
    }
};
