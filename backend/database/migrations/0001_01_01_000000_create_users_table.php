<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username', 40)->nullable()->unique();
            $table->string('email')->unique();
            $table->string('location')->nullable();
            $table->string('bio', 160)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false)->index();
            $table->boolean('is_bot')->default(false)->index();
            $table->string('role', 20)->default('user');
            $table->boolean('is_banned')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('warning_count')->default(0);
            $table->rememberToken();
            $table->timestamps();

            $table->index('username', 'users_username_index');
            $table->index('name', 'users_name_index');
            $table->index(['is_active', 'is_banned'], 'users_active_banned_index');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
