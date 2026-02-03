<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SidebarSection extends Model
{
    protected $fillable = [
        'key',
        'title',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
