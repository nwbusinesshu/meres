<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $table = 'organization';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = [];

    public function users(){
        return $this->belongsToMany(User::class, 'organization_user', 'organization_id', 'user_id')
            ->withPivot('role');
    }
}
