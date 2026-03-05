<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsrfTestRouteRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_test_route_is_not_exposed(): void
    {
        $this->getJson('/api/csrf-test')->assertNotFound();
    }
}