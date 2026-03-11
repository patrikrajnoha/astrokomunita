<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_health_endpoint_returns_ok_and_time(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure([
            'ok',
            'status',
            'app',
            'time',
        ]);
        $response->assertJsonMissingPath('env');
    }

    public function test_debug_health_endpoint_hides_diagnostics_by_default(): void
    {
        $response = $this->getJson('/api/_health');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonStructure([
            'ok',
            'status',
            'app',
            'time',
        ]);
        $response->assertJsonMissingPath('env');
        $response->assertJsonMissingPath('git_sha');
        $response->assertJsonMissingPath('build_id');
    }

    public function test_debug_health_endpoint_can_expose_diagnostics_when_enabled(): void
    {
        config()->set('security.health.expose_diagnostics', true);

        $response = $this->getJson('/api/_health');

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('env', app()->environment());
        $response->assertJsonStructure([
            'ok',
            'status',
            'app',
            'env',
            'time',
            'git_sha',
            'build_id',
        ]);
    }
}
