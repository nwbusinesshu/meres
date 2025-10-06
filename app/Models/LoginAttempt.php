<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    protected $table = 'login_attempts';
    
    protected $fillable = [
        'email',
        'ip_address',
        'failed_attempts',
        'locked_until',
        'last_attempt_at',
    ];
    
    protected $casts = [
        'locked_until' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    /**
     * Check if the account is currently locked
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        if (is_null($this->locked_until)) {
            return false;
        }
        
        return $this->locked_until->isFuture();
    }

    /**
     * Get remaining lockout time in minutes
     *
     * @return int
     */
    public function getRemainingLockoutMinutes(): int
    {
        if (!$this->isLocked()) {
            return 0;
        }
        
        return max(0, (int) $this->locked_until->diffInMinutes(now(), false) * -1);
    }
}