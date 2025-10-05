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
     * 
     * SECURITY FIX: Uses hash_equals() for constant-time comparison
     * to prevent timing attacks that could reveal partial verification codes.
     */
    public static function verifyCode(string $email, string $code, string $sessionId): bool
    {
        // Retrieve the verification code record without comparing the code value in the WHERE clause
        // This prevents timing attacks on the database query itself
        $verificationCode = static::where('email', $email)
            ->where('session_id', $sessionId)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        // Use constant-time comparison to prevent timing attacks
        // hash_equals() takes the same amount of time regardless of where strings differ
        if ($verificationCode && hash_equals($verificationCode->code, $code)) {
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