<?php

namespace App\Models;

use App\Services\Storage\MediaStorageService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
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
        'bio',
        'location',
        'latitude',
        'longitude',
        'timezone',
        'is_admin',
        'is_bot',
        'role',
        'is_banned',
        'banned_at',
        'ban_reason',
        'is_active',
        'warning_count',
        'last_calendar_popup_at',
        'calendar_popup_last_force_version',
        'newsletter_subscribed',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
        'cover_url',
        'location_meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_bot' => 'boolean',
            'is_banned' => 'boolean',
            'banned_at' => 'datetime',
            'is_active' => 'boolean',
            'warning_count' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'last_calendar_popup_at' => 'datetime',
            'calendar_popup_last_force_version' => 'integer',
            'newsletter_subscribed' => 'boolean',
        ];
    }

    public function getAvatarUrlAttribute(): ?string
    {
        $path = $this->avatar_path;
        if (!$path) {
            return null;
        }

        $media = app(MediaStorageService::class);
        if (!$media->exists($path)) {
            return null;
        }

        return $media->absoluteUrl($path);
    }

    public function getCoverUrlAttribute(): ?string
    {
        $path = $this->cover_path;
        if (!$path) {
            return null;
        }

        $media = app(MediaStorageService::class);
        if (!$media->exists($path)) {
            return null;
        }

        return $media->absoluteUrl($path);
    }

    public function getLocationMetaAttribute(): ?array
    {
        $rawLocation = trim((string) ($this->location ?? ''));
        $fallbackTimezone = (string) config('user_locations.fallback_timezone', 'Europe/Bratislava');

        $explicitLat = is_numeric($this->latitude) ? (float) $this->latitude : null;
        $explicitLon = is_numeric($this->longitude) ? (float) $this->longitude : null;
        $explicitTz = $this->resolveValidTimezone($this->timezone) ?? $fallbackTimezone;

        if ($explicitLat !== null && $explicitLon !== null) {
            return [
                'name' => $rawLocation !== '' ? $rawLocation : 'Custom location',
                'lat' => $explicitLat,
                'lon' => $explicitLon,
                'tz' => $explicitTz,
            ];
        }

        if ($rawLocation === '') {
            return null;
        }

        $known = config('user_locations.map', []);
        $item = null;

        if (isset($known[$rawLocation]) && is_array($known[$rawLocation])) {
            $item = $known[$rawLocation];
        } else {
            $item = $this->resolveLocationMetaFromNormalizedMap($rawLocation, $known);
        }

        if (is_array($item)) {
            $lat = isset($item['lat']) && is_numeric($item['lat']) ? (float) $item['lat'] : null;
            $lon = isset($item['lon']) && is_numeric($item['lon']) ? (float) $item['lon'] : null;
            $tz = $this->resolveValidTimezone($item['tz'] ?? null) ?? $fallbackTimezone;

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
            'tz' => $explicitTz,
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

    /**
     * @return list<string>
     */
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

    private function resolveValidTimezone(mixed $value): ?string
    {
        $raw = is_string($value) ? trim($value) : '';
        if ($raw === '') {
            return null;
        }

        return in_array($raw, timezone_identifiers_list(), true) ? $raw : null;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_likes');
    }

    public function bookmarks(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_user_bookmarks')
            ->withPivot('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function pollVotes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function eventPreference(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function newsletterRuns(): HasMany
    {
        return $this->hasMany(NewsletterRun::class, 'admin_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || (bool) $this->is_admin;
    }

    public function isBanned(): bool
    {
        return !is_null($this->banned_at) || (bool) $this->is_banned;
    }

    public function isBot(): bool
    {
        return (bool) $this->is_bot;
    }
}
