<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory, HasCompanyScope;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'value',
        'applied_to',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected $casts = [
        'value' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
