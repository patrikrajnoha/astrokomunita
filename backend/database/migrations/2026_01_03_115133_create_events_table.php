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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('type');
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->dateTime('max_at')->nullable();
            $table->text('short')->nullable();
            $table->longText('description')->nullable();
            $table->integer('visibility')->default(1);
            $table->string('source_name')->nullable()->index();
            $table->string('source_uid')->nullable()->index();
            $table->string('source_hash')->nullable()->index();
            $table->timestamps();

            $table->unique(['source_name', 'source_uid'], 'events_source_name_uid_unique');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
