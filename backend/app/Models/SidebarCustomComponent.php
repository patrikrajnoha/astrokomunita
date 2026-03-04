<?php

namespace App\Models;

use App\Support\SidebarWidgetConfigSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidebarCustomComponent extends Model
{
    public const TYPE_CTA = SidebarWidgetConfigSchema::TYPE_CTA;
    public const TYPE_INFO_CARD = SidebarWidgetConfigSchema::TYPE_INFO_CARD;
    public const TYPE_LINK_LIST = SidebarWidgetConfigSchema::TYPE_LINK_LIST;
    public const TYPE_HTML = SidebarWidgetConfigSchema::TYPE_HTML;

    public const TYPE_SPECIAL_EVENT = SidebarWidgetConfigSchema::LEGACY_TYPE_SPECIAL_EVENT;

    protected $fillable = [
        'name',
        'type',
        'config_json',
        'is_active',
    ];

    protected $casts = [
        'config_json' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function normalizeType(?string $type): string
    {
        return SidebarWidgetConfigSchema::normalizeType($type);
    }

    /**
     * @return array<int, string>
     */
    public static function acceptedInputTypes(): array
    {
        return SidebarWidgetConfigSchema::acceptedInputTypes();
    }

    /**
     * @return array<int, string>
     */
    public static function widgetTypes(): array
    {
        return SidebarWidgetConfigSchema::widgetTypes();
    }

    public function sidebarConfigs(): HasMany
    {
        return $this->hasMany(SidebarSectionConfig::class, 'custom_component_id');
    }
}
