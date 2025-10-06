<?php

namespace App\Services;

use App\Models\LoginAttempt;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Login Attempt Service
 * 
 * Handles persistent account lockout mechanism based on failed login attempts.
 * Tracks attempts per email + IP address combination.
 * 
 * Configuration via .env:
 * - LOGIN_MAX_ATTEMPTS: Maximum failed attempts before lockout (default: 5)
 * - LOGIN_LOCKOUT_MINUTES: Lockout duration in minutes (default: 30)
 * - LOGIN_DECAY_MINUTES: Time window for attempts to count (default: 60)
 */
class LoginAttemptService
{
    /**
     * Check if an account is locked
     *
     * @param string $email
     * @param string $ipAddress
     * @return array ['locked' => bool, 'minutes_remaining' => int]
     */
    public static function isLocked(string $email, string $ipAddress): array
    {
        $attempt = self::getAttempt($email, $ipAddress);
        
        if (!$attempt) {
            return ['locked' => false, 'minutes_remaining' => 0];
        }
        
        // Check if locked and not expired
        if ($attempt->isLocked()) {
            return [
                'locked' => true,
                'minutes_remaining' => $attempt->getRemainingLockoutMinutes()
            ];
        }
        
        // Check if lockout has expired - auto-unlock
        if (!is_null($attempt->locked_until) && !$attempt->isLocked()) {
            self::clearAttempts($email, $ipAddress);
            return ['locked' => false, 'minutes_remaining' => 0];
        }
        
        return ['locked' => false, 'minutes_remaining' => 0];
    }

    /**
     * Record a failed login attempt
     *
     * @param string $email
     * @param string $ipAddress
     * @return array ['locked' => bool, 'attempts' => int, 'minutes_remaining' => int]
     */
    public static function recordFailedAttempt(string $email, string $ipAddress): array
    {
        $maxAttempts = (int) env('LOGIN_MAX_ATTEMPTS', 5);
        $lockoutMinutes = (int) env('LOGIN_LOCKOUT_MINUTES', 30);
        $decayMinutes = (int) env('LOGIN_DECAY_MINUTES', 60);
        
        $attempt = self::getAttempt($email, $ipAddress);
        
        if (!$attempt) {
            // Create new attempt record
            $attempt = LoginAttempt::create([
                'email' => $email,
                'ip_address' => $ipAddress,
                'failed_attempts' => 1,
                'last_attempt_at' => now(),
            ]);
            
            Log::info('login_attempt.first_failed', [
                'email' => $email,
                'ip' => $ipAddress,
            ]);
            
            return [
                'locked' => false,
                'attempts' => 1,
                'minutes_remaining' => 0
            ];
        }
        
        // Check if attempts should decay (older than decay window)
        if ($attempt->last_attempt_at && $attempt->last_attempt_at->diffInMinutes(now()) > $decayMinutes) {
            // Reset counter if last attempt was too long ago
            $attempt->failed_attempts = 1;
            $attempt->locked_until = null;
            $attempt->last_attempt_at = now();
            $attempt->save();
            
            Log::info('login_attempt.reset_after_decay', [
                'email' => $email,
                'ip' => $ipAddress,
                'decay_minutes' => $decayMinutes,
            ]);
            
            return [
                'locked' => false,
                'attempts' => 1,
                'minutes_remaining' => 0
            ];
        }
        
        // Increment failed attempts
        $attempt->failed_attempts += 1;
        $attempt->last_attempt_at = now();
        
        // Lock account if max attempts reached
        if ($attempt->failed_attempts >= $maxAttempts) {
            $attempt->locked_until = now()->addMinutes($lockoutMinutes);
            
            Log::warning('login_attempt.account_locked', [
                'email' => $email,
                'ip' => $ipAddress,
                'failed_attempts' => $attempt->failed_attempts,
                'locked_until' => $attempt->locked_until,
            ]);
        } else {
            Log::info('login_attempt.failed', [
                'email' => $email,
                'ip' => $ipAddress,
                'failed_attempts' => $attempt->failed_attempts,
                'remaining_attempts' => $maxAttempts - $attempt->failed_attempts,
            ]);
        }
        
        $attempt->save();
        
        return [
            'locked' => $attempt->isLocked(),
            'attempts' => $attempt->failed_attempts,
            'minutes_remaining' => $attempt->getRemainingLockoutMinutes()
        ];
    }

    /**
     * Clear failed attempts on successful login
     *
     * @param string $email
     * @param string $ipAddress
     * @return void
     */
    public static function clearAttempts(string $email, string $ipAddress): void
    {
        $deleted = LoginAttempt::where('email', $email)
            ->where('ip_address', $ipAddress)
            ->delete();
        
        if ($deleted > 0) {
            Log::info('login_attempt.cleared', [
                'email' => $email,
                'ip' => $ipAddress,
            ]);
        }
    }

    /**
     * Clear all attempts for an email (admin unlock)
     *
     * @param string $email
     * @return int Number of records deleted
     */
    public static function adminUnlock(string $email): int
    {
        $deleted = LoginAttempt::where('email', $email)->delete();
        
        if ($deleted > 0) {
            Log::info('login_attempt.admin_unlock', [
                'email' => $email,
                'records_deleted' => $deleted,
            ]);
        }
        
        return $deleted;
    }

    /**
     * Get login attempt record
     *
     * @param string $email
     * @param string $ipAddress
     * @return LoginAttempt|null
     */
    protected static function getAttempt(string $email, string $ipAddress): ?LoginAttempt
    {
        return LoginAttempt::where('email', $email)
            ->where('ip_address', $ipAddress)
            ->first();
    }

    /**
     * Clean up expired lockouts (can be called from scheduled task)
     *
     * @return int Number of records cleaned
     */
    public static function cleanupExpiredLockouts(): int
    {
        $deleted = LoginAttempt::where('locked_until', '<', now())
            ->whereNotNull('locked_until')
            ->delete();
        
        if ($deleted > 0) {
            Log::info('login_attempt.cleanup', [
                'records_deleted' => $deleted,
            ]);
        }
        
        return $deleted;
    }

    /**
     * Get lockout status for display (admin view)
     *
     * @param string $email
     * @return array
     */
    public static function getLockoutStatus(string $email): array
    {
        $attempts = LoginAttempt::where('email', $email)->get();
        
        $locked = [];
        $active = [];
        
        foreach ($attempts as $attempt) {
            $data = [
                'ip_address' => $attempt->ip_address,
                'failed_attempts' => $attempt->failed_attempts,
                'last_attempt_at' => $attempt->last_attempt_at,
                'locked_until' => $attempt->locked_until,
                'is_locked' => $attempt->isLocked(),
                'minutes_remaining' => $attempt->getRemainingLockoutMinutes(),
            ];
            
            if ($attempt->isLocked()) {
                $locked[] = $data;
            } else {
                $active[] = $data;
            }
        }
        
        return [
            'email' => $email,
            'locked' => $locked,
            'active' => $active,
            'total_attempts' => count($attempts),
        ];
    }
}