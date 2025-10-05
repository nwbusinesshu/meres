<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationProfile extends Model
{
    protected $table = 'organization_profiles';

    protected $fillable = [
        'organization_id',
        'country_code',
        'postal_code',
        'region',
        'city',
        'street',
        'house_number',
        'phone',
        'employee_limit',
        'tax_number',
        'eu_vat_number',
        'subscription_type',
        'created_at',
        'updated_at',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}