<?php

namespace App\Support;

class SidebarSectionRegistry
{
    public const SCOPE_HOME = 'home';
    public const SCOPE_EVENTS = 'events';
    public const SCOPE_CALENDAR = 'calendar';
    public const SCOPE_LEARNING = 'learning';
    public const SCOPE_SEARCH = 'search';
    public const SCOPE_NOTIFICATIONS = 'notifications';
    public const SCOPE_POST_DETAIL = 'post_detail';
    public const SCOPE_ARTICLE_DETAIL = 'article_detail';
    public const SCOPE_PROFILE = 'profile';
    public const SCOPE_SETTINGS = 'settings';
    public const SCOPE_OBSERVING = 'observing';

    /** @return array<int, string> */
    public static function allScopes(): array
    {
        return [
            self::SCOPE_HOME,
            self::SCOPE_EVENTS,
            self::SCOPE_CALENDAR,
            self::SCOPE_LEARNING,
            self::SCOPE_SEARCH,
            self::SCOPE_NOTIFICATIONS,
            self::SCOPE_POST_DETAIL,
            self::SCOPE_ARTICLE_DETAIL,
            self::SCOPE_PROFILE,
            self::SCOPE_SETTINGS,
            self::SCOPE_OBSERVING,
        ];
    }

    /** @return array<int, string> */
    public static function scopes(): array
    {
        return self::allScopes();
    }

    /**
     * @return array<int, array{section_key:string,title:string,default_enabled:bool,default_order:int}>
     */
    public static function sections(): array
    {
        return [
            [
                'section_key' => 'observing_conditions',
                'title' => 'Astronomicke podmienky',
                'default_enabled' => true,
                'default_order' => 0,
            ],
            [
                'section_key' => 'observing_weather',
                'title' => 'Pocasie pre pozorovanie',
                'default_enabled' => true,
                'default_order' => 1,
            ],
            [
                'section_key' => 'night_sky',
                'title' => 'Nocna obloha',
                'default_enabled' => true,
                'default_order' => 2,
            ],
            [
                'section_key' => 'iss_pass',
                'title' => 'ISS prelet',
                'default_enabled' => true,
                'default_order' => 3,
            ],
            [
                'section_key' => 'search',
                'title' => 'Hladat',
                'default_enabled' => true,
                'default_order' => 4,
            ],
            [
                'section_key' => 'nasa_apod',
                'title' => 'NASA Novinky',
                'default_enabled' => true,
                'default_order' => 5,
            ],
            [
                'section_key' => 'next_event',
                'title' => 'Najblizsia udalost',
                'default_enabled' => true,
                'default_order' => 6,
            ],
            [
                'section_key' => 'latest_articles',
                'title' => 'Najnovsie clanky',
                'default_enabled' => true,
                'default_order' => 7,
            ],
            [
                'section_key' => 'upcoming_events',
                'title' => 'Co sa deje',
                'default_enabled' => true,
                'default_order' => 8,
            ],
            [
                'section_key' => 'moon_phases',
                'title' => 'Fazy mesiaca',
                'default_enabled' => true,
                'default_order' => 9,
            ],
            [
                'section_key' => 'space_weather',
                'title' => 'Vesmirne pocasie',
                'default_enabled' => false,
                'default_order' => 14,
            ],
            [
                'section_key' => 'aurora_watch',
                'title' => 'Aurora watch',
                'default_enabled' => false,
                'default_order' => 15,
            ],
            [
                'section_key' => 'neo_watchlist',
                'title' => 'NEO watchlist',
                'default_enabled' => false,
                'default_order' => 16,
            ],
            [
                'section_key' => 'next_eclipse',
                'title' => 'Najblizsie zatmenie',
                'default_enabled' => false,
                'default_order' => 12,
            ],
            [
                'section_key' => 'next_meteor_shower',
                'title' => 'Najblizsi meteoricky roj',
                'default_enabled' => false,
                'default_order' => 13,
            ],
            [
                'section_key' => 'moon_overview',
                'title' => 'Mesiac teraz',
                'default_enabled' => false,
                'default_order' => 10,
            ],
            [
                'section_key' => 'moon_events',
                'title' => 'Lunarne udalosti',
                'default_enabled' => false,
                'default_order' => 11,
            ],
        ];
    }

    public static function isValidScope(?string $scope): bool
    {
        return is_string($scope) && in_array($scope, self::allScopes(), true);
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
