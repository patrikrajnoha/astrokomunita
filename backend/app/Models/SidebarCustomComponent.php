<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SidebarCustomComponent extends Model
{
    public const TYPE_SPECIAL_EVENT = 'special_event';

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

    public function sidebarConfigs(): HasMany
    {
        return $this->hasMany(SidebarSectionConfig::class, 'custom_component_id');
    }
}

