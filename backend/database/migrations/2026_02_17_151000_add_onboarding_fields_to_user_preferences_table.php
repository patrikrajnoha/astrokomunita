<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->json('interests')->nullable()->after('event_types');
            $table->string('location_label')->nullable()->after('region');
            $table->string('location_place_id')->nullable()->after('location_label');
            $table->decimal('location_lat', 10, 6)->nullable()->after('location_place_id');
            $table->decimal('location_lon', 10, 6)->nullable()->after('location_lat');
            $table->timestamp('onboarding_completed_at')->nullable()->after('location_lon')->index();
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropIndex(['onboarding_completed_at']);
            $table->dropColumn([
                'interests',
                'location_label',
                'location_place_id',
                'location_lat',
                'location_lon',
                'onboarding_completed_at',
            ]);
        });
    }
};
