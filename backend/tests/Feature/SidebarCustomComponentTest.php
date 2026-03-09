<?php

namespace Tests\Feature;

use App\Models\SidebarCustomComponent;
use App\Models\SidebarSectionConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        $create = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Hero CTA',
            'type' => SidebarCustomComponent::TYPE_CTA,
            'is_active' => true,
            'config_json' => [
                'headline' => 'Join our astronomy meetup',
                'body' => 'Weekly updates and practical observing tips.',
                'buttonText' => 'Open group',
                'buttonHref' => '/community',
                'imageUrl' => 'https://example.com/hero.jpg',
                'icon' => 'rocket',
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.type', SidebarCustomComponent::TYPE_CTA)
            ->assertJsonPath('data.config_json.headline', 'Join our astronomy meetup')
            ->assertJsonPath('data.config_json.buttonHref', '/community');

        $componentId = (int) $create->json('data.id');

        $update = $this->putJson('/api/admin/sidebar/custom-components/'.$componentId, [
            'name' => 'Useful links',
            'type' => SidebarCustomComponent::TYPE_LINK_LIST,
            'is_active' => false,
            'config_json' => [
                'title' => 'Resources',
                'links' => [
                    ['label' => 'Calendar', 'href' => '/calendar'],
                    ['label' => 'Events', 'href' => '/events'],
                ],
            ],
        ]);

        $update->assertOk()
            ->assertJsonPath('data.name', 'Useful links')
            ->assertJsonPath('data.type', SidebarCustomComponent::TYPE_LINK_LIST)
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.config_json.links.0.href', '/calendar');

        $this->assertDatabaseHas('sidebar_custom_components', [
            'id' => $componentId,
            'name' => 'Useful links',
            'type' => SidebarCustomComponent::TYPE_LINK_LIST,
            'is_active' => false,
        ]);

        $delete = $this->deleteJson('/api/admin/sidebar/custom-components/'.$componentId);
        $delete->assertOk();

        $this->assertDatabaseMissing('sidebar_custom_components', [
            'id' => $componentId,
        ]);
    }

    public function test_custom_component_validation_is_type_specific(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $brokenLinkList = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Broken links',
            'type' => SidebarCustomComponent::TYPE_LINK_LIST,
            'config_json' => [
                'title' => 'Missing links payload',
                'links' => [],
            ],
        ]);

        $brokenLinkList->assertStatus(422)->assertJsonValidationErrors([
            'config_json.links',
        ]);

        $brokenCta = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Broken CTA',
            'type' => SidebarCustomComponent::TYPE_CTA,
            'config_json' => [
                'body' => 'Missing required fields',
            ],
        ]);

        $brokenCta->assertStatus(422)->assertJsonValidationErrors([
            'config_json.headline',
            'config_json.buttonText',
            'config_json.buttonHref',
        ]);
    }

    public function test_html_widget_payload_is_sanitized_on_store(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Custom HTML',
            'type' => SidebarCustomComponent::TYPE_HTML,
            'is_active' => true,
            'config_json' => [
                'html' => '<p onclick="alert(1)">Read <a href="javascript:alert(2)">more</a></p><script>alert(3)</script>',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', SidebarCustomComponent::TYPE_HTML);

        $html = (string) $response->json('data.config_json.html');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick=', strtolower($html));
    }

    public function test_admin_can_list_custom_components_and_filter_active_only(): void
    {
        Sanctum::actingAs($this->createAdmin());

        SidebarCustomComponent::query()->create([
            'name' => 'Active info',
            'type' => SidebarCustomComponent::TYPE_INFO_CARD,
            'is_active' => true,
            'config_json' => [
                'title' => 'Tonight',
                'content' => 'Clear sky expected.',
                'icon' => 'moon',
            ],
        ]);

        SidebarCustomComponent::query()->create([
            'name' => 'Inactive html',
            'type' => SidebarCustomComponent::TYPE_HTML,
            'is_active' => false,
            'config_json' => [
                'html' => '<p>Preview</p>',
            ],
        ]);

        $all = $this->getJson('/api/admin/sidebar/custom-components');
        $all->assertOk()->assertJsonCount(2, 'data');

        $activeOnly = $this->getJson('/api/admin/sidebar/custom-components?active_only=1');
        $activeOnly->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Active info');
    }

    public function test_sidebar_custom_component_endpoints_are_admin_only(): void
    {
        $payload = [
            'name' => 'Forbidden widget',
            'type' => SidebarCustomComponent::TYPE_CTA,
            'is_active' => true,
            'config_json' => [
                'headline' => 'Forbidden',
                'body' => 'No admin',
                'buttonText' => 'Open',
                'buttonHref' => '/events',
                'imageUrl' => null,
                'icon' => null,
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
        $component = SidebarCustomComponent::query()->create([
            'name' => 'Info card runtime',
            'type' => SidebarCustomComponent::TYPE_INFO_CARD,
            'is_active' => true,
            'config_json' => [
                'title' => 'Observing tip',
                'content' => 'Take a red flashlight.',
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
                'type' => SidebarCustomComponent::TYPE_INFO_CARD,
            ])
            ->assertJsonFragment([
                'title' => 'Observing tip',
                'content' => 'Take a red flashlight.',
            ]);
    }

    public function test_legacy_special_event_type_is_mapped_to_cta_schema(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Legacy special event',
            'type' => SidebarCustomComponent::TYPE_SPECIAL_EVENT,
            'config_json' => [
                'title' => 'Special Event',
                'description' => 'Legacy payload',
                'eventId' => 42,
                'buttonLabel' => 'Open detail',
                'buttonTarget' => '',
                'imageUrl' => '',
                'icon' => 'calendar',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', SidebarCustomComponent::TYPE_CTA)
            ->assertJsonPath('data.config_json.headline', 'Special Event')
            ->assertJsonPath('data.config_json.body', 'Legacy payload')
            ->assertJsonPath('data.config_json.buttonText', 'Open detail')
            ->assertJsonPath('data.config_json.buttonHref', '/events/42');
    }

    public function test_admin_can_create_contest_sidebar_widget(): void
    {
        Sanctum::actingAs($this->createAdmin());

        $response = $this->postJson('/api/admin/sidebar/custom-components', [
            'name' => 'Sutaz marec',
            'type' => SidebarCustomComponent::TYPE_CONTEST,
            'is_active' => true,
            'config_json' => [
                'title' => 'Sutaz o teleskop',
                'description' => 'Pridaj fotku oblohy a vyhraj.',
                'imageUrl' => '/api/media/file/sidebar-widgets/1/sutaz.jpg',
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', SidebarCustomComponent::TYPE_CONTEST)
            ->assertJsonPath('data.config_json.title', 'Sutaz o teleskop')
            ->assertJsonPath('data.config_json.description', 'Pridaj fotku oblohy a vyhraj.')
            ->assertJsonPath('data.config_json.imageUrl', '/api/media/file/sidebar-widgets/1/sutaz.jpg');
    }

    public function test_admin_can_upload_contest_sidebar_image(): void
    {
        Storage::fake('public');
        config(['media.disk' => 'public']);
        Sanctum::actingAs($this->createAdmin());

        $response = $this->post('/api/admin/sidebar/custom-components/upload-image', [
            'file' => UploadedFile::fake()->create('contest-cover.jpg', 256, 'image/jpeg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Obrazok bol nahrany.');

        $path = (string) $response->json('data.path');
        $url = (string) $response->json('data.url');

        $this->assertNotSame('', $path);
        $this->assertStringStartsWith('sidebar-widgets/', $path);
        $this->assertStringContainsString('/api/media/file/', $url);
        Storage::disk('public')->assertExists($path);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
        ]);
    }
}
