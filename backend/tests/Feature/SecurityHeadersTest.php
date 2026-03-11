<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_api_responses_include_default_security_headers(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_header_is_added_for_secure_requests(): void
    {
        config()->set('session.secure', true);

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_security_headers_can_be_disabled_via_config(): void
    {
        config()->set('security.headers.enabled', false);

        $response = $this->getJson('/api/health');

        $response->assertOk();
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('X-Content-Type-Options');
        $response->assertHeaderMissing('Referrer-Policy');
        $response->assertHeaderMissing('Permissions-Policy');
        $response->assertHeaderMissing('X-Permitted-Cross-Domain-Policies');
    }
}
