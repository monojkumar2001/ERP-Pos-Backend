<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class UomConversion extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'from_uom', 'to_uom', 'factor', 'status'];
    protected $casts = ['factor' => 'decimal:6', 'status' => 'boolean'];
}
