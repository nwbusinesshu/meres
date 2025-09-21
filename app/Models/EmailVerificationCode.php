<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmailVerificationCode extends Model
{
    protected $table = 'email_verification_codes';
    
    protected $fillable = [
        'email',
        'code',
        'session_id',
        'expires_at',
        'used_at',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Generate a new 6-digit verification code
     */
    public static function generateCode(): string
    {
        return str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new verification code for email
     */
    public static function createForEmail(string $email, string $sessionId, string $ipAddress = null, string $userAgent = null): self
    {
        // Invalidate any existing codes for this email/session
        static::where('email', $email)
            ->where('session_id', $sessionId)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return static::create([
            'email' => $email,
            'code' => static::generateCode(),
            'session_id' => $sessionId,
            'expires_at' => now()->addMinutes(10), // Code expires in 10 minutes
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Verify a code for the given email and session
     */
    public static function verifyCode(string $email, string $code, string $sessionId): bool
    {
        $verificationCode = static::where('email', $email)
            ->where('code', $code)
            ->where('session_id', $sessionId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($verificationCode) {
            $verificationCode->update(['used_at' => now()]);
            return true;
        }

        return false;
    }

    /**
     * Check if there's a pending verification for email/session
     */
    public static function hasPendingVerification(string $email, string $sessionId): bool
    {
        return static::where('email', $email)
            ->where('session_id', $sessionId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Clean up expired codes (can be called via scheduled command)
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now()->subDay())->delete();
    }
}