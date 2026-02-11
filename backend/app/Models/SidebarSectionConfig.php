<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SidebarSectionConfig extends Model
{
    protected $fillable = [
        'scope',
        'section_key',
        'order',
        'is_enabled',
    ];

    protected $casts = [
        'order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }
}
