<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Color extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'color_code', 'status'];
    protected $casts = ['status' => 'boolean'];
}
