<?php

namespace Tests\Unit\Support\Http;

use App\Support\Http\SslVerificationPolicy;
use Tests\TestCase;

class SslVerificationPolicyTest extends TestCase
{
    public function test_it_can_disable_ssl_verification_in_local_environment_only_when_enabled(): void
    {
        config()->set('http_client.allow_insecure_ssl', true);
        $this->app['env'] = 'local';

        $this->assertFalse(app(SslVerificationPolicy::class)->shouldVerifySsl());
    }

    public function test_it_can_disable_ssl_verification_in_testing_environment_only_when_enabled(): void
    {
        config()->set('http_client.allow_insecure_ssl', true);
        $this->app['env'] = 'testing';

        $this->assertFalse(app(SslVerificationPolicy::class)->shouldVerifySsl());
    }

    public function test_it_always_verifies_ssl_in_production_even_when_insecure_flag_is_enabled(): void
    {
        config()->set('http_client.allow_insecure_ssl', true);
        $this->app['env'] = 'production';

        $this->assertTrue(app(SslVerificationPolicy::class)->shouldVerifySsl());
    }
}
