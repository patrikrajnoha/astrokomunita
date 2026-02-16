<?php

namespace Tests\Feature;

use App\Enums\EventSource as EventSourceEnum;
use App\Models\EventSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEventSourceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);
    }

    public function test_admin_can_list_event_sources(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.test',
            'is_enabled' => true,
        ]);
        EventSource::query()->create([
            'key' => EventSourceEnum::GO_ASTRONOMY->value,
            'name' => EventSourceEnum::GO_ASTRONOMY->label(),
            'base_url' => 'https://go-astronomy.test',
            'is_enabled' => false,
        ]);

        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/event-sources');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.key', EventSourceEnum::ASTROPIXELS->value);
    }

    public function test_admin_can_toggle_source_enabled_state(): void
    {
        $source = EventSource::query()->create([
            'key' => EventSourceEnum::GO_ASTRONOMY->value,
            'name' => EventSourceEnum::GO_ASTRONOMY->label(),
            'base_url' => 'https://go-astronomy.test',
            'is_enabled' => true,
        ]);

        $this->actingAsAdmin();

        $this->patchJson("/api/admin/event-sources/{$source->id}", [
            'is_enabled' => false,
        ])->assertOk()
            ->assertJsonPath('key', EventSourceEnum::GO_ASTRONOMY->value)
            ->assertJsonPath('is_enabled', false);

        $this->assertDatabaseHas('event_sources', [
            'id' => $source->id,
            'is_enabled' => false,
        ]);
    }

    public function test_manual_run_executes_enabled_source_and_skips_disabled_source(): void
    {
        EventSource::query()->create([
            'key' => EventSourceEnum::ASTROPIXELS->value,
            'name' => EventSourceEnum::ASTROPIXELS->label(),
            'base_url' => 'https://astropixels.com/almanac/almanac21/almanac%dcet.html',
            'is_enabled' => true,
        ]);
        EventSource::query()->create([
            'key' => EventSourceEnum::GO_ASTRONOMY->value,
            'name' => EventSourceEnum::GO_ASTRONOMY->label(),
            'base_url' => 'https://go-astronomy.test/calendar',
            'is_enabled' => false,
        ]);

        $this->actingAsAdmin();

        $html = File::get(base_path('tests/Fixtures/astropixels/almanac2026cet.html'));
        Http::fake([
            'https://astropixels.com/*' => Http::response($html, 200),
        ]);

        $response = $this->postJson('/api/admin/event-sources/run', [
            'source_keys' => [
                EventSourceEnum::ASTROPIXELS->value,
                EventSourceEnum::GO_ASTRONOMY->value,
            ],
            'year' => 2026,
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonPath('results.0.source_key', EventSourceEnum::ASTROPIXELS->value);
        $response->assertJsonPath('results.0.status', 'success');
        $response->assertJsonPath('results.1.source_key', EventSourceEnum::GO_ASTRONOMY->value);
        $response->assertJsonPath('results.1.status', 'skipped');

        $this->assertDatabaseHas('crawl_runs', [
            'source_name' => EventSourceEnum::ASTROPIXELS->value,
            'status' => 'success',
        ]);
    }
}
