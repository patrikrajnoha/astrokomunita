<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'date_of_birth',
        'email',
        'password',
        'avatar_path',
        'cover_path',
        'bio',        // Twitter-like "O mne"
        'location',   // Poloha používateľa
        'is_admin',   // Role
        'is_bot',     // Automated bot user (AstroBot)
        'role',
        'is_banned',
        'is_active',
        'warning_count',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
        'cover_url',
        'location_meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed', // Laravel automaticky hashne heslo
            'is_admin' => 'boolean',
            'is_bot' => 'boolean',
            'is_banned' => 'boolean',
            'is_active' => 'boolean',
            'warning_count' => 'integer',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->avatar_path;
        if (!$path) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return null;
        }

        return $disk->url($path);
    }

    public function getCoverUrlAttribute(): ?string
    {
        $path = $this->cover_path;
        if (!$path) {
            return null;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return null;
        }

        return $disk->url($path);
    }

    public function getLocationMetaAttribute(): ?array
    {
        $rawLocation = trim((string) ($this->location ?? ''));
        if ($rawLocation === '') {
            return null;
        }

        $known = config('user_locations.map', []);
        $fallbackTimezone = (string) config('user_locations.fallback_timezone', 'Europe/Bratislava');
        $item = null;

        if (isset($known[$rawLocation]) && is_array($known[$rawLocation])) {
            $item = $known[$rawLocation];
        } else {
            $item = $this->resolveLocationMetaFromNormalizedMap($rawLocation, $known);
        }

        if (is_array($item)) {
            $lat = isset($item['lat']) && is_numeric($item['lat']) ? (float) $item['lat'] : null;
            $lon = isset($item['lon']) && is_numeric($item['lon']) ? (float) $item['lon'] : null;
            $tz = is_string($item['tz'] ?? null) && trim($item['tz']) !== ''
                ? trim($item['tz'])
                : $fallbackTimezone;

            if ($lat !== null && $lon !== null) {
                return [
                    'name' => $rawLocation,
                    'lat' => $lat,
                    'lon' => $lon,
                    'tz' => $tz,
                ];
            }
        }

        return [
            'name' => $rawLocation,
            'lat' => null,
            'lon' => null,
            'tz' => $fallbackTimezone,
        ];
    }

    private function resolveLocationMetaFromNormalizedMap(string $rawLocation, array $known): ?array
    {
        $normalizedMap = [];

        foreach ($known as $name => $candidate) {
            if (!is_array($candidate)) {
                continue;
            }

            $normalizedKey = $this->normalizeLocationLookup((string) $name);
            if ($normalizedKey !== '') {
                $normalizedMap[$normalizedKey] = $candidate;
            }
        }

        foreach ($this->locationLookupCandidates($rawLocation) as $candidateLookup) {
            if (isset($normalizedMap[$candidateLookup])) {
                return $normalizedMap[$candidateLookup];
            }

            foreach ($normalizedMap as $knownName => $knownMeta) {
                if (str_starts_with($candidateLookup, $knownName . ' ')) {
                    return $knownMeta;
                }
            }
        }

        return null;
    }

    private function locationLookupCandidates(string $rawLocation): array
    {
        $candidates = [];
        $normalized = $this->normalizeLocationLookup($rawLocation);

        if ($normalized !== '') {
            $candidates[] = $normalized;
        }

        $withoutCountrySuffix = preg_replace('/\s*,\s*(sk|slovakia|slovensko|cz|czechia|czech republic)\s*$/i', '', $rawLocation);
        $withoutCountrySuffix = is_string($withoutCountrySuffix) ? $withoutCountrySuffix : $rawLocation;
        $normalizedWithoutCountry = $this->normalizeLocationLookup($withoutCountrySuffix);

        if ($normalizedWithoutCountry !== '' && !in_array($normalizedWithoutCountry, $candidates, true)) {
            $candidates[] = $normalizedWithoutCountry;
        }

        $beforeComma = trim((string) Str::of($rawLocation)->before(','));
        $normalizedBeforeComma = $this->normalizeLocationLookup($beforeComma);

        if ($normalizedBeforeComma !== '' && !in_array($normalizedBeforeComma, $candidates, true)) {
            $candidates[] = $normalizedBeforeComma;
        }

        return $candidates;
    }

    private function normalizeLocationLookup(string $value): string
    {
        $ascii = Str::of($value)->ascii()->lower()->value();
        $clean = preg_replace('/[^a-z0-9]+/i', ' ', $ascii);
        $clean = is_string($clean) ? trim(preg_replace('/\s+/', ' ', $clean) ?? '') : '';

        return $clean;
    }

    /**
     * Posty, ktorĂ˝ pouĹľĂ­vateÄľ lajkol.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_likes');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function eventPreference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->is_admin;
    }

    public function isBanned(): bool
    {
        return (bool) $this->is_banned;
    }

    /**
     * Check if user is a bot (e.g., AstroBot).
     * Bot users publish automated content and should not receive replies.
     */
    public function isBot(): bool
    {
        return (bool) $this->is_bot;
    }
}
