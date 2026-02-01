<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('is_admin');
            $table->boolean('is_banned')->default(false)->after('role')->index();
            $table->boolean('is_active')->default(true)->after('is_banned')->index();
        });

        DB::table('users')
            ->where('is_admin', true)
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_banned']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'is_banned', 'is_active']);
        });
    }
};
