<?php

namespace App\Models;

use App\Services\Storage\MediaStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ObservationMedia extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'observation_media';

    protected $fillable = [
        'observation_id',
        'path',
        'mime_type',
        'width',
        'height',
        'created_at',
    ];

    protected $casts = [
        'observation_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'created_at' => 'datetime',
    ];

    protected $appends = [
        'url',
    ];

    public function observation(): BelongsTo
    {
        return $this->belongsTo(Observation::class);
    }

    public function getUrlAttribute(): ?string
    {
        $path = trim((string) $this->path);
        if ($path === '') {
            return null;
        }

        return app(MediaStorageService::class)->absoluteUrl($path);
    }
}
