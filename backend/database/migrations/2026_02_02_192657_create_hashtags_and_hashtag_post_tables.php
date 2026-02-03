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
        // Tabuľka pre hashtagy
        Schema::create('hashtags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            
            // Index pre rýchle vyhľadávanie podľa mena
            $table->index('name');
        });

        // Pivot tabuľka pre spojenie posts a hashtags
        Schema::create('hashtag_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hashtag_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            // Zabezpečíme, že každý pár je unikátny
            $table->unique(['hashtag_id', 'post_id']);
            
            // Indexy pre rýchle dotazy
            $table->index('hashtag_id');
            $table->index('post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hashtag_post');
        Schema::dropIfExists('hashtags');
    }
};
