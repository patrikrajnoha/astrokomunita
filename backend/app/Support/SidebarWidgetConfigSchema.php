<?php

namespace App\Support;

/**
 * Sidebar has two separate domains:
 * 1) layout builder (scope + ordered section references),
 * 2) reusable custom widget definitions stored in sidebar_custom_components.
 *
 * This schema class owns the custom widget definition contract.
 */
class SidebarWidgetConfigSchema
{
    public const TYPE_CTA = 'cta';
    public const TYPE_INFO_CARD = 'info_card';
    public const TYPE_LINK_LIST = 'link_list';
    public const TYPE_HTML = 'html';

    public const LEGACY_TYPE_SPECIAL_EVENT = 'special_event';

    /**
     * @return array<int, string>
     */
    public static function widgetTypes(): array
    {
        return [
            self::TYPE_CTA,
            self::TYPE_INFO_CARD,
            self::TYPE_LINK_LIST,
            self::TYPE_HTML,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function acceptedInputTypes(): array
    {
        return [
            ...self::widgetTypes(),
            self::LEGACY_TYPE_SPECIAL_EVENT,
        ];
    }

    public static function normalizeType(?string $type): string
    {
        $raw = strtolower(trim((string) $type));

        if ($raw === self::LEGACY_TYPE_SPECIAL_EVENT) {
            return self::TYPE_CTA;
        }

        return in_array($raw, self::widgetTypes(), true)
            ? $raw
            : self::TYPE_CTA;
    }

    public static function normalizeTypeOrNull(?string $type): ?string
    {
        $raw = strtolower(trim((string) $type));

        if ($raw === '') {
            return null;
        }

        if ($raw === self::LEGACY_TYPE_SPECIAL_EVENT) {
            return self::TYPE_CTA;
        }

        return in_array($raw, self::widgetTypes(), true) ? $raw : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(string $normalizedType): array
    {
        return match ($normalizedType) {
            self::TYPE_CTA => [
                'config_json.headline' => ['required', 'string', 'max:140'],
                'config_json.body' => ['required', 'string', 'max:400'],
                'config_json.buttonText' => ['required', 'string', 'max:90'],
                'config_json.buttonHref' => ['required', 'string', 'max:255', self::pathOrUrlValidationRule()],
                'config_json.imageUrl' => ['nullable', 'string', 'max:2048', self::pathOrUrlValidationRule()],
                'config_json.icon' => ['nullable', 'string', 'max:80'],
            ],
            self::TYPE_INFO_CARD => [
                'config_json.title' => ['required', 'string', 'max:140'],
                'config_json.content' => ['required', 'string', 'max:500'],
                'config_json.icon' => ['nullable', 'string', 'max:80'],
            ],
            self::TYPE_LINK_LIST => [
                'config_json.title' => ['required', 'string', 'max:140'],
                'config_json.links' => ['required', 'array', 'min:1', 'max:12'],
                'config_json.links.*.label' => ['required', 'string', 'max:120'],
                'config_json.links.*.href' => ['required', 'string', 'max:255', self::pathOrUrlValidationRule()],
            ],
            self::TYPE_HTML => [
                'config_json.html' => ['required', 'string', 'max:12000'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalizeConfig(string $normalizedType, array $config): array
    {
        return match ($normalizedType) {
            self::TYPE_CTA => self::normalizeCtaConfig($config),
            self::TYPE_INFO_CARD => self::normalizeInfoCardConfig($config),
            self::TYPE_LINK_LIST => self::normalizeLinkListConfig($config),
            self::TYPE_HTML => self::normalizeHtmlConfig($config),
            default => self::normalizeCtaConfig($config),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeCtaConfig(array $config): array
    {
        $headline = self::sanitizeString($config['headline'] ?? $config['title'] ?? null);
        $body = self::sanitizeString($config['body'] ?? $config['description'] ?? null);
        $buttonText = self::sanitizeString($config['buttonText'] ?? $config['buttonLabel'] ?? null);

        $buttonHref = self::sanitizePathOrUrl($config['buttonHref'] ?? $config['buttonTarget'] ?? null);
        $legacyEventId = self::normalizeInt($config['eventId'] ?? null);
        if ($buttonHref === null && $legacyEventId !== null) {
            $buttonHref = '/events/'.$legacyEventId;
        }

        return [
            'headline' => $headline,
            'body' => $body,
            'buttonText' => $buttonText,
            'buttonHref' => $buttonHref,
            'imageUrl' => self::sanitizePathOrUrl($config['imageUrl'] ?? null),
            'icon' => self::sanitizeString($config['icon'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeInfoCardConfig(array $config): array
    {
        return [
            'title' => self::sanitizeString($config['title'] ?? null),
            'content' => self::sanitizeString($config['content'] ?? null),
            'icon' => self::sanitizeString($config['icon'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeLinkListConfig(array $config): array
    {
        $links = [];
        foreach (array_values(is_array($config['links'] ?? null) ? $config['links'] : []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $links[] = [
                'label' => self::sanitizeString($item['label'] ?? null),
                'href' => self::sanitizePathOrUrl($item['href'] ?? null),
            ];
        }

        return [
            'title' => self::sanitizeString($config['title'] ?? null),
            'links' => $links,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function normalizeHtmlConfig(array $config): array
    {
        return [
            'html' => self::sanitizeHtml($config['html'] ?? null),
        ];
    }

    private static function sanitizeString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim(strip_tags($value));

        return $trimmed === '' ? null : $trimmed;
    }

    private static function sanitizePathOrUrl(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $trimmed)) {
            return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : null;
        }

        if (str_starts_with($trimmed, '/')) {
            return '/'.ltrim($trimmed, '/');
        }

        return $trimmed;
    }

    private static function sanitizeHtml(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $allowedTags = '<p><br><strong><em><ul><ol><li><a><span><b><i>';
        $clean = strip_tags($trimmed, $allowedTags);
        $clean = preg_replace('/on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? $clean;

        $normalized = trim($clean);

        return $normalized === '' ? null : $normalized;
    }

    private static function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return null;
    }

    private static function pathOrUrlValidationRule(): \Closure
    {
        return static function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if (!is_string($value)) {
                $fail("The {$attribute} field must be a string.");
                return;
            }

            $trimmed = trim($value);
            $isUrl = filter_var($trimmed, FILTER_VALIDATE_URL) && preg_match('/^https?:\\/\\//i', $trimmed);
            $isPath = str_starts_with($trimmed, '/');

            if (!$isUrl && !$isPath) {
                $fail("The {$attribute} field must be a valid URL or an absolute path.");
            }
        };
    }
}
