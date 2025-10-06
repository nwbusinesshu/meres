<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordSetup extends Model
{
    protected $table = 'password_setup';
    public $timestamps = false;

    /**
     * SECURITY FIX: Removed $guarded = [] and set empty $fillable
     * 
     * This model should NEVER use mass assignment. All fields are security-critical:
     * - organization_id: Prevents cross-organization attacks
     * - user_id: Prevents password reset for other users
     * - token_hash: The actual reset token - must never be mass-assignable
     * - created_by: Audit trail - must not be spoofed
     * - created_at: Security timing - must be controlled
     * - expires_at: Token expiry - attacker could extend indefinitely
     * - used_at: Token usage flag - attacker could reuse tokens
     * 
     * All creation/updates must use explicit property assignment:
     *   $setup = new PasswordSetup();
     *   $setup->user_id = $userId;
     *   $setup->save();
     * 
     * Or use DB::table('password_setup')->insert() as currently done in PasswordSetupService
     */
    protected $fillable = [];
    
    protected $dates = ['created_at', 'expires_at', 'used_at'];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}