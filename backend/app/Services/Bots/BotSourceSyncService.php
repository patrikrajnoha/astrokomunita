<?php

namespace App\Services\Bots;

use App\Enums\BotSourceType;
use App\Enums\PostBotIdentity;
use App\Models\BotSource;

class BotSourceSyncService
{
    /**
     * @return array{created:int,updated:int,total:int}
     */
    public function syncDefaults(): array
    {
        $definitions = [
            [
                'key' => 'nasa_rss_breaking',
                'bot_identity' => PostBotIdentity::KOZMO->value,
                'source_type' => BotSourceType::RSS->value,
                'url' => (string) config('astrobot.nasa_rss_url', 'https://www.nasa.gov/news-release/feed/'),
                'is_enabled' => true,
                'schedule' => null,
            ],
            [
                'key' => 'nasa_apod_daily',
                'bot_identity' => PostBotIdentity::STELA->value,
                'source_type' => BotSourceType::API->value,
                'url' => (string) config('astrobot.nasa_apod_url', 'https://api.nasa.gov/planetary/apod'),
                'is_enabled' => true,
                'schedule' => null,
            ],
            [
                'key' => 'wiki_onthisday_astronomy',
                'bot_identity' => PostBotIdentity::KOZMO->value,
                'source_type' => BotSourceType::WIKIPEDIA->value,
                'url' => (string) config('astrobot.wikipedia_onthisday_url', 'https://api.wikimedia.org/feed/v1/wikipedia/en/onthisday/all'),
                'is_enabled' => true,
                'schedule' => null,
            ],
        ];

        $created = 0;
        $updated = 0;

        foreach ($definitions as $definition) {
            $source = BotSource::query()->where('key', $definition['key'])->first();
            if (!$source) {
                BotSource::query()->create($definition);
                $created++;
                continue;
            }

            $dirty = false;
            foreach ($definition as $field => $value) {
                if ($source->{$field} !== $value) {
                    $source->{$field} = $value;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $source->save();
                $updated++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => count($definitions),
        ];
    }
}

