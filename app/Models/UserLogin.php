<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserLogin extends Pivot
{
    protected $table = 'user_login';
    
    public $timestamps = false;
    
    /**
     * SECURITY FIX: Removed $guarded = [] and implemented strict $fillable whitelist
     * 
     * This model stores login audit trail for security tracking.
     * 
     * Mass-assignable fields:
     * - user_id: The user who logged in
     * - logged_in_at: Timestamp of login
     * - token: Session token for tracking
     * - ip: IP address of login
     * - user_agent: Browser/device information
     * 
     * Note: All fields should be set during login creation.
     * This is an audit log, so records should never be updated after creation.
     */
    protected $fillable = [
        'user_id',
        'logged_in_at',
        'token',
        'ip',
        'user_agent',
    ];
    
    protected $hidden = [];
      
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}