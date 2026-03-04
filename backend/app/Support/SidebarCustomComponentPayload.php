<?php

namespace App\Support;

use App\Models\SidebarCustomComponent;

class SidebarCustomComponentPayload
{
    public static function toArray(SidebarCustomComponent $component): array
    {
        $normalizedType = SidebarWidgetConfigSchema::normalizeType((string) $component->type);
        $config = self::normalizeConfig($component, $normalizedType);

        return [
            'id' => (int) $component->id,
            'name' => (string) $component->name,
            'type' => $normalizedType,
            'is_active' => (bool) $component->is_active,
            'config_json' => $config,
            'config' => $config,
            'created_at' => optional($component->created_at)?->toIso8601String(),
            'updated_at' => optional($component->updated_at)?->toIso8601String(),
        ];
    }

    public static function normalizeConfig(SidebarCustomComponent $component, ?string $normalizedType = null): array
    {
        $config = is_array($component->config_json) ? $component->config_json : [];
        $type = $normalizedType ?: SidebarWidgetConfigSchema::normalizeType((string) $component->type);

        return SidebarWidgetConfigSchema::normalizeConfig($type, $config);
    }
}
