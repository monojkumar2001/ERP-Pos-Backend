<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryChannel extends Model
{
    use HasFactory, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'name',
        'provider',
        'credentials',
        'status',
    ];

    protected $casts = [
        'credentials' => 'array',
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveryCharges()
    {
        return $this->hasMany(DeliveryCharge::class, 'channel_id');
    }
}
