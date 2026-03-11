<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_data_export_jobs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 24)->default('pending')->index();
            $table->string('request_ip_hash', 128)->nullable();
            $table->string('file_path', 512)->nullable();
            $table->string('file_name', 191)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum_sha256', 128)->nullable();
            $table->string('error_message', 512)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_data_export_jobs');
    }
};
