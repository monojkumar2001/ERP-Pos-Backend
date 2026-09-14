<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'discount', 'status'];
    protected $casts = ['status' => 'boolean'];
}
