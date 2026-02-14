<?php

use App\Enums\RegionScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete()->unique();
            $table->json('event_types')->nullable();
            $table->string('region', 16)->default(RegionScope::Global->value)->index();
            $table->timestamps();

            $table->index(['user_id', 'region'], 'user_preferences_user_region_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
