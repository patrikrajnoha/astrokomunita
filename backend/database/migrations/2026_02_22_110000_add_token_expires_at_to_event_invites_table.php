<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_invites', function (Blueprint $table) {
            $table->timestamp('token_expires_at')->nullable()->after('token');
            $table->index('token_expires_at');
        });

        DB::table('event_invites')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    if ($row->created_at === null) {
                        continue;
                    }

                    $createdAt = Carbon::parse((string) $row->created_at);
                    DB::table('event_invites')
                        ->where('id', $row->id)
                        ->update([
                            'token_expires_at' => $createdAt->copy()->addDays(14),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('event_invites', function (Blueprint $table) {
            $table->dropIndex(['token_expires_at']);
            $table->dropColumn('token_expires_at');
        });
    }
};
