<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('current_email');
            $table->string('new_email');
            $table->timestamp('current_email_confirmed_at')->nullable();
            $table->timestamp('new_email_applied_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at'], 'email_change_requests_user_expires_idx');
            $table->index(['user_id', 'completed_at'], 'email_change_requests_user_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
