<?php

namespace App\Services;

/**
 * Builds LLM prompts for blog tag suggestion.
 *
 * All methods are pure (no side effects, no I/O) and depend only on their inputs.
 */
class BlogTagPromptBuilder
{
    private const MAX_INPUT_TAG_LENGTH = 80;

    /**
     * Prompt for selecting from existing tags only.
     *
     * @param array<int,string> $existingTagNames
     * @param array<int,string> $alreadyAttachedTagNames
     */
    public function buildPrompt(
        string $title,
        string $content,
        array $existingTagNames,
        array $alreadyAttachedTagNames
    ): string {
        $input = [
            'article' => [
                'title' => $title,
                'content_excerpt' => $content,
            ],
            'existing_tags' => array_values(array_unique(array_filter(array_map(
                fn (string $name): string => $this->sanitizeText($name, self::MAX_INPUT_TAG_LENGTH),
                $existingTagNames
            )))),
            'already_attached_tags' => array_values(array_unique(array_filter(array_map(
                fn (string $name): string => $this->sanitizeText($name, self::MAX_INPUT_TAG_LENGTH),
                $alreadyAttachedTagNames
            )))),
        ];

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Úloha:
- Vyber najrelevantnejšie EXISTUJÚCE tagy pre Learn/Blog článok.
- Použi iba názvy z poľa existing_tags.
- Nenavrhuj už pripojené tagy z already_attached_tags.
- Maximálne 5 tagov.

Vráť STRICT JSON objekt presne v tvare:
{"tags":[{"name":"...","reason":"..."}]}

Pravidlá:
- tags: pole 0 až 5 položiek
- name: presná zhoda s názvom z existing_tags
- reason: krátke zdôvodnenie, max 120 znakov
- bez markdownu, bez komentárov, bez ďalších kľúčov

Input JSON:
{$inputJson}
PROMPT;
    }

    /**
     * Prompt for selecting from existing tags or suggesting new ones.
     *
     * @param array<int,string> $existingTagNames
     * @param array<int,string> $alreadyAttachedTagNames
     */
    public function buildFlexiblePrompt(
        string $title,
        string $content,
        array $existingTagNames,
        array $alreadyAttachedTagNames
    ): string {
        $input = [
            'article' => [
                'title' => $title,
                'content_excerpt' => $content,
            ],
            'existing_tags' => array_values(array_unique(array_filter(array_map(
                fn (string $name): string => $this->sanitizeText($name, self::MAX_INPUT_TAG_LENGTH),
                $existingTagNames
            )))),
            'already_attached_tags' => array_values(array_unique(array_filter(array_map(
                fn (string $name): string => $this->sanitizeText($name, self::MAX_INPUT_TAG_LENGTH),
                $alreadyAttachedTagNames
            )))),
        ];

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Úloha:
- Navrhni najrelevantnejšie tagy pre Learn/Blog článok.
- Môžeš vybrať existujúci tag z existing_tags ALEBO navrhnúť nový.
- Nenavrhuj už pripojené tagy z already_attached_tags.
- Maximálne 5 tagov.

Vráť STRICT JSON objekt presne v tvare:
{"tags":[{"name":"...","reason":"..."}]}

Pravidlá:
- tags: pole 0 až 5 položiek
- name: krátky názov tagu, max 40 znakov
- reason: krátke zdôvodnenie, max 120 znakov
- bez markdownu, bez komentárov, bez ďalších kľúčov

Input JSON:
{$inputJson}
PROMPT;
    }

    /**
     * Prompt for suggesting completely new tags from scratch.
     */
    public function buildOpenPrompt(string $title, string $content): string
    {
        $input = [
            'article' => [
                'title' => $title,
                'content_excerpt' => $content,
            ],
        ];

        $inputJson = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($inputJson) || trim($inputJson) === '') {
            $inputJson = '{}';
        }

        return <<<PROMPT
Úloha:
- Navrhni najrelevantnejšie tagy pre Learn/Blog článok podľa textu.
- Tag má byť krátky (1 až 3 slová), konkrétny, bez znaku #.
- Maximálne 5 tagov.
- Nenavrhuj príliš všeobecné slová ako "článok" alebo "obsah".

Vráť STRICT JSON objekt presne v tvare:
{"tags":[{"name":"...","reason":"..."}]}

Pravidlá:
- tags: pole 0 až 5 položiek
- name: max 40 znakov
- reason: krátke zdôvodnenie, max 120 znakov
- bez markdownu, bez komentárov, bez ďalších kľúčov

Input JSON:
{$inputJson}
PROMPT;
    }

    public function systemPromptForExistingTags(): string
    {
        return 'Si editor blogu. Vyberáš len existujúce tagy, bez vymýšľania nových.';
    }

    public function systemPromptForOpenTags(): string
    {
        return 'Si editor blogu. Navrhuj konkrétne tagy podľa textu článku.';
    }

    public function systemPromptForFlexibleTags(): string
    {
        return 'Si editor blogu. Preferuj existujúce tagy, no môžeš navrhnúť aj nové.';
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $plain = trim(strip_tags($value));
        if ($plain === '') {
            return '';
        }

        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;
        return $this->truncateUtf8(trim($plain), max(1, $maxLength));
    }

    private function truncateUtf8(string $value, int $maxLength): string
    {
        if ($maxLength <= 0 || $value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}
