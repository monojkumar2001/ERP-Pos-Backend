<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasCompanyScope;

    protected $fillable = ['company_id', 'parent_id', 'name', 'position', 'status'];
    protected $casts = ['status' => 'boolean', 'position' => 'integer'];

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name'); }
}
