<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationProfile extends Model
{

    protected $table = 'organization_profiles';

    protected $fillable = [
        'organization_id',
        'tax_number',
        'billing_address',
        'subscription_type',
        'created_at',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}