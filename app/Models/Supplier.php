<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasCompanyScope;
    protected $fillable = [
        'company_id', 'name', 'phone', 'email', 'code', 'supplier_type_id',
        'opening_balance', 'contact_name', 'address', 'city', 'state', 'zip',
        'country', 'status',
    ];
    protected $casts = ['opening_balance' => 'decimal:2', 'status' => 'boolean'];

    public function type() { return $this->belongsTo(SupplierType::class, 'supplier_type_id'); }
}
