<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BotAvatarAssetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_avatar_asset_endpoint_serves_existing_png(): void
    {
        $response = $this->get('/api/bot-avatars/kozmobot/kb_blue.png');

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
    }

    public function test_bot_avatar_asset_endpoint_returns_not_found_for_invalid_file(): void
    {
        $this->get('/api/bot-avatars/kozmobot/missing.png')->assertNotFound();
    }
}

