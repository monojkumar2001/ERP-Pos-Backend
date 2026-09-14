<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class PriceList extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'transaction_type', 'pricebook_type', 'status'];
    protected $casts = ['status' => 'boolean'];
}
