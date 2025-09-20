<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CookieConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'ip_address',
        'necessary',
        'analytics',
        'marketing',
        'preferences',
        'consent_date',
        'consent_version',
        'user_agent',
    ];

    protected $casts = [
        'necessary' => 'boolean',
        'analytics' => 'boolean',
        'marketing' => 'boolean',
        'preferences' => 'boolean',
        'consent_date' => 'datetime',
        'user_agent' => 'array',
    ];

    /**
     * Get the user that owns the consent.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the latest consent for a session or user
     */
    public static function getLatestConsent($sessionId = null, $userId = null)
    {
        $query = static::query();

        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_id', $sessionId);
        } else {
            return null;
        }

        return $query->latest('consent_date')->first();
    }

    /**
     * Check if specific cookie type is consented
     */
    public function hasConsent(string $type): bool
    {
        return match ($type) {
            'necessary' => true, // Always true
            'analytics' => $this->analytics,
            default => false, // marketing and preferences are not used
        };
    }

    /**
     * Get all consented cookie types
     */
    public function getConsentedTypes(): array
    {
        $types = ['necessary']; // Always included

        if ($this->analytics) {
            $types[] = 'analytics';
        }

        return $types;
    }

    /**
     * Check if analytics is enabled
     */
    public function hasAnalytics(): bool
    {
        return $this->analytics;
    }

    /**
     * Check if only necessary cookies are enabled
     */
    public function isNecessaryOnly(): bool
    {
        return !$this->analytics;
    }
}