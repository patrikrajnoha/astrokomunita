<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location');
            }

            if (!Schema::hasColumn('users', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('longitude');
            }

            if (!Schema::hasColumn('users', 'location_label')) {
                $table->string('location_label', 80)->nullable()->after('timezone');
            }

            if (!Schema::hasColumn('users', 'location_source')) {
                $table->string('location_source', 16)->nullable()->after('location_label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'location_source')) {
                $table->dropColumn('location_source');
            }

            if (Schema::hasColumn('users', 'location_label')) {
                $table->dropColumn('location_label');
            }
        });
    }
};

