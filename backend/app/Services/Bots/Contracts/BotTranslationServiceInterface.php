<?php

namespace App\Services\Bots\Contracts;

interface BotTranslationServiceInterface
{
    /**
     * @return array{
     *   translated_title:?string,
     *   translated_content:?string,
     *   title_translated:?string,
     *   content_translated:?string,
     *   status:string,
     *   meta:array<string, mixed>
     * }
     */
    public function translate(?string $title, ?string $content, string $to = 'sk'): array;
}
