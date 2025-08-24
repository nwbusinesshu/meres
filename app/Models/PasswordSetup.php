<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordSetup extends Model
{
    protected $table = 'password_setup';
    public $timestamps = false;

    protected $guarded = [];
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
