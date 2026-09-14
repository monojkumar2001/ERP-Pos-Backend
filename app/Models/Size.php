<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Size extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'size_type_id', 'name', 'position', 'status'];
    protected $casts = ['status' => 'boolean', 'position' => 'integer'];

    public function sizeType() { return $this->belongsTo(SizeType::class); }
}
