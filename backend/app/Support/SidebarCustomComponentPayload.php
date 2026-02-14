<?php

namespace App\Support;

use App\Models\Event;
use App\Models\SidebarCustomComponent;

class SidebarCustomComponentPayload
{
    public static function toArray(SidebarCustomComponent $component): array
    {
        $config = self::normalizeConfig($component);
        $eventSummary = self::eventSummary($config['eventId'] ?? null);

        return [
            'id' => (int) $component->id,
            'name' => (string) $component->name,
            'type' => (string) $component->type,
            'is_active' => (bool) $component->is_active,
            'config_json' => $config,
            'event_summary' => $eventSummary,
            'created_at' => optional($component->created_at)?->toIso8601String(),
            'updated_at' => optional($component->updated_at)?->toIso8601String(),
        ];
    }

    public static function normalizeConfig(SidebarCustomComponent $component): array
    {
        $config = is_array($component->config_json) ? $component->config_json : [];
        $eventId = isset($config['eventId']) && is_numeric($config['eventId']) ? (int) $config['eventId'] : null;
        $buttonTarget = isset($config['buttonTarget']) ? trim((string) $config['buttonTarget']) : '';

        if ($buttonTarget === '' && $eventId) {
            $buttonTarget = '/events/'.$eventId;
        }

        return [
            'title' => trim((string) ($config['title'] ?? '')),
            'description' => trim((string) ($config['description'] ?? '')),
            'eventId' => $eventId,
            'buttonLabel' => trim((string) ($config['buttonLabel'] ?? '')),
            'buttonTarget' => $buttonTarget,
            'imageUrl' => trim((string) ($config['imageUrl'] ?? '')),
            'icon' => trim((string) ($config['icon'] ?? '')),
        ];
    }

    public static function eventSummary(?int $eventId): ?array
    {
        if (!$eventId) {
            return null;
        }

        $event = Event::query()->find($eventId);
        if (!$event) {
            return null;
        }

        return [
            'id' => (int) $event->id,
            'title' => (string) $event->title,
            'start_at' => optional($event->start_at)?->toIso8601String(),
            'max_at' => optional($event->max_at)?->toIso8601String(),
        ];
    }
}

