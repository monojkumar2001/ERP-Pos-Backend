<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class CustomerType extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function customers() { return $this->hasMany(Customer::class); }
}
