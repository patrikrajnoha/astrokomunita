<?php

namespace Tests\Feature;

use Tests\TestCase;

class ObserveDiagnosticsTest extends TestCase
{
    public function test_diagnostics_endpoint_returns_404_outside_local(): void
    {
        $this->getJson('/api/observe/diagnostics?lat=48.1486&lon=17.1077&date=2026-02-10&tz=Europe/Bratislava')
            ->assertStatus(404);
    }
}

