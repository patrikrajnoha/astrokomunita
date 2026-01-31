<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'title',
        'type',
        'start_at',
        'end_at',
        'max_at',
        'short',
        'description',
        'visibility',
        'source_name',
        'source_uid',
        'source_hash',
    ];
    /**
     * Atribúty, ktoré by mali byť pretypované.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_at'   => 'datetime',
        'end_at'     => 'datetime',
        'max_at'     => 'datetime',
        'visibility' => 'integer',
    ];

    /**
     * Scope pre odfiltrovanie len publikovaných udalostí (zo zdroja).
     * Zabezpečí, že seed dáta a pracovné verzie sa nedostanú na frontend.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('source_name')
                     ->whereNotNull('source_uid');
    }

    /**
     * Vzťah k obľúbeným položkám.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
