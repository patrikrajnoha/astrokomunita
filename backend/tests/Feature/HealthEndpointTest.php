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
            'env',
            'time',
        ]);
    }

    public function test_debug_health_endpoint_returns_env_and_revision_fields(): void
    {
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
