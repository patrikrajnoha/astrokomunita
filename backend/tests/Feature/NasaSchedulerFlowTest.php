<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class NasaSchedulerFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_uses_only_astrobot_nasa_sync_job_for_nasa_source(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('astrobot:nasa:sync-job', $output);
        $this->assertStringNotContainsString('news:import-nasa', $output);
    }
}
