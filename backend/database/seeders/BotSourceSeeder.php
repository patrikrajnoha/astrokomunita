<?php

namespace Database\Seeders;

use App\Enums\BotSourceType;
use App\Enums\PostBotIdentity;
use App\Models\BotSource;
use Illuminate\Database\Seeder;

class BotSourceSeeder extends Seeder
{
    public function run(): void
    {
        BotSource::query()->updateOrCreate(
            ['key' => 'nasa_rss_breaking'],
            [
                'bot_identity' => PostBotIdentity::KOZMO->value,
                'source_type' => BotSourceType::RSS->value,
                'url' => (string) config('astrobot.nasa_rss_url', 'https://www.nasa.gov/news-release/feed/'),
                'is_enabled' => true,
                'schedule' => null,
            ]
        );

        BotSource::query()->updateOrCreate(
            ['key' => 'nasa_apod_daily'],
            [
                'bot_identity' => PostBotIdentity::STELA->value,
                'source_type' => BotSourceType::API->value,
                'url' => (string) config('astrobot.nasa_apod_url', 'https://api.nasa.gov/planetary/apod'),
                'is_enabled' => true,
                'schedule' => null,
            ]
        );

        BotSource::query()->updateOrCreate(
            ['key' => 'wiki_onthisday_astronomy'],
            [
                'bot_identity' => PostBotIdentity::KOZMO->value,
                'source_type' => BotSourceType::WIKIPEDIA->value,
                'url' => (string) config('astrobot.wikipedia_onthisday_url', 'https://api.wikimedia.org/feed/v1/wikipedia/en/onthisday/all'),
                'is_enabled' => true,
                'schedule' => null,
            ]
        );
    }
}
