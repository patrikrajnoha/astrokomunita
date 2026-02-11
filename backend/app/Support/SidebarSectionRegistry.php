<?php

namespace App\Support;

class SidebarSectionRegistry
{
    public const SCOPE_HOME = 'home';
    public const SCOPE_EVENTS = 'events';
    public const SCOPE_CALENDAR = 'calendar';
    public const SCOPE_LEARNING = 'learning';
    public const SCOPE_NOTIFICATIONS = 'notifications';

    /** @return array<int, string> */
    public static function scopes(): array
    {
        return [
            self::SCOPE_HOME,
            self::SCOPE_EVENTS,
            self::SCOPE_CALENDAR,
            self::SCOPE_LEARNING,
            self::SCOPE_NOTIFICATIONS,
        ];
    }

    /**
     * @return array<int, array{section_key:string,title:string,default_enabled:bool,default_order:int}>
     */
    public static function sections(): array
    {
        return [
            [
                'section_key' => 'search',
                'title' => 'Search',
                'default_enabled' => true,
                'default_order' => 0,
            ],
            [
                'section_key' => 'observing_conditions',
                'title' => 'Observing Conditions',
                'default_enabled' => true,
                'default_order' => 1,
            ],
            [
                'section_key' => 'nasa_apod',
                'title' => 'NASA APOD',
                'default_enabled' => true,
                'default_order' => 2,
            ],
            [
                'section_key' => 'next_event',
                'title' => 'Next Event',
                'default_enabled' => true,
                'default_order' => 3,
            ],
            [
                'section_key' => 'latest_articles',
                'title' => 'Latest Articles',
                'default_enabled' => true,
                'default_order' => 4,
            ],
        ];
    }

    public static function isValidScope(?string $scope): bool
    {
        return is_string($scope) && in_array($scope, self::scopes(), true);
    }

    public static function isValidSectionKey(string $sectionKey): bool
    {
        foreach (self::sections() as $section) {
            if ($section['section_key'] === $sectionKey) {
                return true;
            }
        }

        return false;
    }

    public static function sectionByKey(string $sectionKey): ?array
    {
        foreach (self::sections() as $section) {
            if ($section['section_key'] === $sectionKey) {
                return $section;
            }
        }

        return null;
    }

    /**
     * @return array<int, array{section_key:string,title:string,order:int,is_enabled:bool}>
     */
    public static function defaultConfig(): array
    {
        $items = array_map(static fn (array $section) => [
            'section_key' => $section['section_key'],
            'title' => $section['title'],
            'order' => $section['default_order'],
            'is_enabled' => $section['default_enabled'],
        ], self::sections());

        usort($items, static fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return array_values($items);
    }
}
