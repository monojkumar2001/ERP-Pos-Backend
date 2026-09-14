<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class SupplierType extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function suppliers() { return $this->hasMany(Supplier::class); }
}
