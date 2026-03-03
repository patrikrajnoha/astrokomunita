<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_change_request_id')
                ->nullable()
                ->constrained('email_change_requests')
                ->nullOnDelete();
            $table->string('email');
            $table->string('purpose', 48)->default('account_verification');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
            $table->index(['user_id', 'purpose'], 'email_verifications_user_purpose_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};
