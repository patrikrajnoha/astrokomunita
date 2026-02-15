<?php

namespace Tests\Feature;

use App\Models\TranslationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranslationHealthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_translation_health_returns_last_success_and_error_rate(): void
    {
        TranslationLog::query()->create([
            'provider' => 'libretranslate',
            'status' => 'failed',
            'error_code' => 'libretranslate_http_500',
            'duration_ms' => 101,
            'language_from' => 'en',
            'language_to' => 'sk',
            'original_text_hash' => hash('sha256', 'failed'),
            'created_at' => now()->subHours(2),
        ]);

        TranslationLog::query()->create([
            'provider' => 'argos_microservice',
            'status' => 'success',
            'error_code' => null,
            'duration_ms' => 70,
            'language_from' => 'en',
            'language_to' => 'sk',
            'original_text_hash' => hash('sha256', 'ok'),
            'created_at' => now()->subHour(),
        ]);

        $oldLog = TranslationLog::query()->create([
            'provider' => 'argos_microservice',
            'status' => 'success',
            'error_code' => null,
            'duration_ms' => 50,
            'language_from' => 'en',
            'language_to' => 'sk',
            'original_text_hash' => hash('sha256', 'old'),
        ]);
        $oldLog->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/translation-health');

        $response->assertOk();
        $response->assertJsonPath('counts_24h.total', 2);
        $response->assertJsonPath('counts_24h.failed', 1);
        $response->assertJsonPath('error_rate_24h_percent', 50);
        $response->assertJsonPath('last_successful_translation.provider', 'argos_microservice');
    }
}
