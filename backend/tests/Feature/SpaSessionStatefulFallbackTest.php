<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaSessionStatefulFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://api.astrokomunita.test');
        config()->set('session.domain', '.astrokomunita.test');
        config()->set('sanctum.stateful', [
            'astrokomunita.test',
            'api.astrokomunita.test',
        ]);
    }

    public function test_logged_in_session_can_access_preferences_without_origin_or_referer_headers(): void
    {
        $user = User::factory()->create();

        $this->withServerVariables([
            'HTTP_HOST' => 'api.astrokomunita.test',
            'HTTPS' => 'on',
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->withServerVariables([
            'HTTP_HOST' => 'api.astrokomunita.test',
            'HTTPS' => 'on',
        ])->getJson('/api/me/preferences')
            ->assertOk()
            ->assertJsonPath('data.has_preferences', false);
    }

    public function test_logged_in_admin_session_can_access_admin_stats_without_origin_or_referer_headers(): void
    {
        $admin = User::factory()->admin()->create();

        $this->withServerVariables([
            'HTTP_HOST' => 'api.astrokomunita.test',
            'HTTPS' => 'on',
        ])->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk();

        $this->withServerVariables([
            'HTTP_HOST' => 'api.astrokomunita.test',
            'HTTPS' => 'on',
        ])->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure([
                'kpi',
                'demographics',
                'trend',
                'generated_at',
            ]);
    }
}
