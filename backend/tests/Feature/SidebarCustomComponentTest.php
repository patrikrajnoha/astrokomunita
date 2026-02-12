<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\SidebarCustomComponent;
use App\Models\SidebarSectionConfig;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SidebarCustomComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_custom_components_returns_empty_array_on_clean_database(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->getJson('/api/admin/sidebar/custom-components');

        $response->assertOk()->assertJson([
            'data' => [],
        ]);
    }

    public function test_admin_can_create_update_and_delete_custom_component(): void
    {
        Sanctum::actingAs($this->createAdmin());
        $event = $this->createEvent();

        $create = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Special event widget',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => true,
            'config_json' => [
                'title' => 'Special event',
                'description' => 'Short text',
                'eventId' => $event->id,
                'buttonLabel' => 'Open detail',
                'buttonTarget' => '',
                'imageUrl' => '',
                'icon' => 'calendar',
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.type', SidebarCustomComponent::TYPE_SPECIAL_EVENT)
            ->assertJsonPath('data.config_json.buttonTarget', '/events/'.$event->id);

        $componentId = (int) $create->json('data.id');

        $update = $this->putJson('/api/admin/sidebar/custom-components/'.$componentId, [
            'name' => 'Updated widget',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => false,
            'config_json' => [
                'title' => '<b>Updated title</b>',
                'description' => 'Updated text',
                'eventId' => $event->id,
                'buttonLabel' => 'Show',
                'buttonTarget' => '/events/'.$event->id,
                'imageUrl' => '',
                'icon' => '',
            ],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.name', 'Updated widget')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.config_json.title', 'Updated title');

        $this->assertDatabaseHas('sidebar_custom_components', [
            'id' => $componentId,
            'name' => 'Updated widget',
            'is_active' => false,
        ]);

        $delete = $this->deleteJson('/api/admin/sidebar/custom-components/'.$componentId);
        $delete->assertOk();

        $this->assertDatabaseMissing('sidebar_custom_components', [
            'id' => $componentId,
        ]);
    }

    public function test_custom_component_validation_for_special_event_type(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Broken widget',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'config_json' => [
                'description' => 'Missing title and button label',
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'config_json.title',
            'config_json.buttonLabel',
        ]);
    }

    public function test_admin_can_list_custom_components(): void
    {
        Sanctum::actingAs($this->createAdmin());
        $event = $this->createEvent();

        SidebarCustomComponent::query()->create([
            'name' => 'Active special',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => true,
            'config_json' => [
                'title' => 'Active',
                'description' => 'Text',
                'eventId' => $event->id,
                'buttonLabel' => 'Open',
                'buttonTarget' => '/events/'.$event->id,
                'imageUrl' => '',
                'icon' => '',
            ],
        ]);

        SidebarCustomComponent::query()->create([
            'name' => 'Inactive special',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => false,
            'config_json' => [
                'title' => 'Inactive',
                'description' => 'Text',
                'eventId' => null,
                'buttonLabel' => 'Open',
                'buttonTarget' => '/events',
                'imageUrl' => '',
                'icon' => '',
            ],
        ]);

        $all = $this->getJson('/api/admin/sidebar/custom-components');
        $all->assertOk()->assertJsonCount(2, 'data');

        $activeOnly = $this->getJson('/api/admin/sidebar/custom-components?active_only=1');
        $activeOnly->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Active special');
    }

    public function test_post_create_custom_component_is_visible_in_following_list_request(): void
    {
        Sanctum::actingAs($this->createAdmin());
        $event = $this->createEvent();

        $create = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Fresh special',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => true,
            'config_json' => [
                'title' => 'Fresh title',
                'description' => 'Fresh description',
                'eventId' => $event->id,
                'buttonLabel' => 'Open',
                'buttonTarget' => '/events/'.$event->id,
                'imageUrl' => '',
                'icon' => '',
            ],
        ]);

        $create->assertCreated();

        $list = $this->getJson('/api/admin/sidebar/custom-components');
        $list->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fresh special');
    }

    public function test_sidebar_custom_component_endpoints_are_admin_only(): void
    {
        $event = $this->createEvent();
        $payload = [
            'name' => 'Forbidden special',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => true,
            'config_json' => [
                'title' => 'Forbidden',
                'description' => 'No admin',
                'eventId' => $event->id,
                'buttonLabel' => 'Open',
                'buttonTarget' => '/events/'.$event->id,
                'imageUrl' => '',
                'icon' => '',
            ],
        ];

        $this->getJson('/api/admin/sidebar/custom-components')->assertStatus(401);
        $this->postJson('/api/admin/sidebar/custom-components', $payload)->assertStatus(401);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'user',
            'is_admin' => false,
        ]));

        $this->getJson('/api/admin/sidebar/custom-components')->assertStatus(403);
        $this->postJson('/api/admin/sidebar/custom-components', $payload)->assertStatus(403);
    }

    public function test_sidebar_config_resolves_custom_component_payload_for_runtime(): void
    {
        $event = $this->createEvent();

        $component = SidebarCustomComponent::query()->create([
            'name' => 'Special Event Card',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'is_active' => true,
            'config_json' => [
                'title' => 'Special Event',
                'description' => 'Description',
                'eventId' => $event->id,
                'buttonLabel' => 'Show detail',
                'buttonTarget' => '',
                'imageUrl' => '',
                'icon' => 'star',
            ],
        ]);

        SidebarSectionConfig::query()->create([
            'scope' => 'home',
            'kind' => 'custom_component',
            'section_key' => 'custom_component',
            'custom_component_id' => $component->id,
            'order' => 5,
            'is_enabled' => true,
        ]);

        $response = $this->getJson('/api/sidebar-config?scope=home');

        $response->assertOk()
            ->assertJsonFragment([
                'kind' => 'custom_component',
                'section_key' => 'custom_component',
                'custom_component_id' => $component->id,
                'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            ])
            ->assertJsonFragment([
                'buttonTarget' => '/events/'.$event->id,
            ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
    }

    private function createEvent(): Event
    {
        return Event::query()->create([
            'title' => 'Test Event',
            'type' => 'meteor_shower',
            'start_at' => CarbonImmutable::now()->addDays(5),
            'max_at' => CarbonImmutable::now()->addDays(5),
            'short' => 'short',
            'description' => 'description',
            'visibility' => 1,
            'source_name' => 'manual',
            'source_uid' => 'evt-'.uniqid(),
        ]);
    }
}
