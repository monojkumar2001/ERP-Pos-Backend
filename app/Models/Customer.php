<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasCompanyScope;
    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'code', 'customer_type_id',
        'website', 'as_supplier', 'price_list_id', 'opening_balance', 'credit_limit',
        'address', 'city', 'state', 'zip', 'country', 'status',
    ];
    protected $casts = [
        'as_supplier' => 'boolean', 'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2', 'status' => 'boolean',
    ];

    public function type() { return $this->belongsTo(CustomerType::class, 'customer_type_id'); }
    public function priceList() { return $this->belongsTo(PriceList::class); }
}
