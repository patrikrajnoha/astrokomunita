<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invitee_email')->nullable();
            $table->string('attendee_name', 80);
            $table->string('message', 240)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->string('token', 120)->nullable()->unique();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'invitee_user_id']);
            $table->index('invitee_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invites');
    }
};
