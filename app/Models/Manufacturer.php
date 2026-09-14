<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Manufacturer extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'description', 'discount', 'status'];
    protected $casts = ['status' => 'boolean'];
}
