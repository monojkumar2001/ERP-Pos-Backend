<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class SizeType extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function sizes() { return $this->hasMany(Size::class)->orderBy('position'); }
}
