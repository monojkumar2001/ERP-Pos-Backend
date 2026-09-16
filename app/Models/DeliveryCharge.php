<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryCharge extends Model
{
    use HasFactory, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'name',
        'channel_id',
        'zone',
        'charge',
        'status',
    ];

    protected $casts = [
        'charge' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function channel()
    {
        return $this->belongsTo(DeliveryChannel::class, 'channel_id');
    }
}
