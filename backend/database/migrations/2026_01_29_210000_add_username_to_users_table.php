<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 40)->nullable()->unique()->after('name');
        });

        $users = DB::table('users')->select('id', 'name', 'email', 'username')->get();
        $used = [];

        foreach ($users as $u) {
            if (!empty($u->username)) {
                $used[$u->username] = true;
                continue;
            }

            $base = trim((string) ($u->name ?? ''));
            if ($base === '') {
                $email = (string) ($u->email ?? '');
                $base = $email !== '' ? explode('@', $email)[0] : 'user';
            }

            $candidate = strtolower($base);
            $candidate = preg_replace('/\s+/', '_', $candidate);
            $candidate = preg_replace('/[^a-z0-9_]+/', '', $candidate);
            $candidate = substr($candidate, 0, 30);
            if ($candidate === '') {
                $candidate = 'user' . $u->id;
            }

            $username = $candidate;
            $i = 1;
            while (isset($used[$username]) || DB::table('users')->where('username', $username)->exists()) {
                $suffix = '_' . $i;
                $username = substr($candidate, 0, 30 - strlen($suffix)) . $suffix;
                $i++;
            }

            DB::table('users')->where('id', $u->id)->update(['username' => $username]);
            $used[$username] = true;
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
