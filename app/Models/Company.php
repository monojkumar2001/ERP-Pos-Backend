<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'email',
        'phone',
        'currency',
        'timezone',
        'status',
        'trial_ends_at',
        'owner_id',
    ];

    protected $casts = [
        'status' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function settings()
    {
        return $this->hasMany(CompanySetting::class);
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function outlets()
    {
        return $this->hasMany(PosOutlet::class);
    }
}
