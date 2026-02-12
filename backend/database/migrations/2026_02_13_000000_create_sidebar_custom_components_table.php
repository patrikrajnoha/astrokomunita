<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sidebar_custom_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('special_event');
            $table->json('config_json');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
            $table->index('type');
            $table->index('is_active');
            $table->unique(['name', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sidebar_custom_components');
    }
};
