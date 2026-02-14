<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SidebarSectionConfig extends Model
{
    protected $fillable = [
        'scope',
        'kind',
        'section_key',
        'custom_component_id',
        'order',
        'is_enabled',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_enabled' => 'boolean',
        'custom_component_id' => 'integer',
    ];

    public function customComponent(): BelongsTo
    {
        return $this->belongsTo(SidebarCustomComponent::class, 'custom_component_id');
    }

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }
}
