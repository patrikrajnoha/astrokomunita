<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_event_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'event_id']);
        });

        if (Schema::hasTable('favorites')) {
            DB::table('user_event_follows')->insertUsing(
                ['user_id', 'event_id', 'created_at', 'updated_at'],
                DB::table('favorites')
                    ->select([
                        'user_id',
                        'event_id',
                        'created_at',
                        'updated_at',
                    ])
                    ->whereNotNull('user_id')
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_event_follows');
    }
};
