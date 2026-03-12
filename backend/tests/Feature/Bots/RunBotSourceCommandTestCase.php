<?php

namespace Tests\Feature\Bots;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Feature\Bots\Concerns\InteractsWithRunBotSourceFixtures;

abstract class RunBotSourceCommandTestCase extends TestCase
{
    use RefreshDatabase;
    use InteractsWithRunBotSourceFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureRunBotSourceDefaults();
    }

}
