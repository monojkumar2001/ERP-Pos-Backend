<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOutlet extends Model
{
    use HasFactory, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'name',
        'code',
        'phone',
        'address',
        'cash_account',
        'mobile_banking',
        'last_user',
        'is_default',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'mobile_banking' => 'array',
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
