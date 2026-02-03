<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
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

    /**
     * Posty, ktorĂ˝ pouĹľĂ­vateÄľ lajkol.
     */
    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_likes');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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
