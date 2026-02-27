<?php

namespace App\Services\Bots;

use App\Enums\BotTranslationStatus;
use App\Services\Bots\Contracts\BotTranslationServiceInterface;

class DummyBotTranslationService implements BotTranslationServiceInterface
{
    public function translate(?string $title, ?string $content, string $to = 'sk'): array
    {
        return [
            'translated_title' => null,
            'translated_content' => null,
            'title_translated' => null,
            'content_translated' => null,
            'status' => BotTranslationStatus::SKIPPED->value,
            'meta' => [
                'provider' => 'dummy',
                'reason' => 'translation_not_enabled',
                'target_lang' => $to,
                'title_present' => filled($title),
                'content_present' => filled($content),
            ],
        ];
    }
}
