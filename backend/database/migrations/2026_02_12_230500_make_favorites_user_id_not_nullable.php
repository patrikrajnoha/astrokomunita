<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('favorites')) {
            return;
        }

        DB::table('favorites')->whereNull('user_id')->delete();

        $hasForeign = $this->hasConstraint('favorites', 'favorites_user_id_foreign');

        Schema::table('favorites', function (Blueprint $table) use ($hasForeign) {
            if ($hasForeign) {
                $table->dropForeign(['user_id']);
            }
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('favorites')) {
            return;
        }

        $hasForeign = $this->hasConstraint('favorites', 'favorites_user_id_foreign');

        Schema::table('favorites', function (Blueprint $table) use ($hasForeign) {
            if ($hasForeign) {
                $table->dropForeign(['user_id']);
            }
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    private function hasConstraint(string $table, string $constraintName): bool
    {
        $databaseName = (string) DB::getDatabaseName();
        if ($databaseName === '') {
            return false;
        }

        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$databaseName, $table, $constraintName, 'FOREIGN KEY']
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }
};
