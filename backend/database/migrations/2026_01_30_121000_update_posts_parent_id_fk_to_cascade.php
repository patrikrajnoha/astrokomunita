<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $fkName = $this->findParentIdForeignKey();

        if ($fkName) {
            Schema::table('posts', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('posts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        $fkName = $this->findParentIdForeignKey();

        if ($fkName) {
            Schema::table('posts', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('posts')
                ->nullOnDelete();
        });
    }

    private function findParentIdForeignKey(): ?string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return null;
        }

        $dbName = DB::getDatabaseName();

        $rows = DB::select(
            'select constraint_name from information_schema.key_column_usage
             where table_schema = ? and table_name = ? and column_name = ? and referenced_table_name = ?',
            [$dbName, 'posts', 'parent_id', 'posts']
        );

        if (empty($rows)) {
            return null;
        }

        return $rows[0]->constraint_name ?? null;
    }
};
