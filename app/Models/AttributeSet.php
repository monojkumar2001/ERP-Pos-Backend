<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class AttributeSet extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'name', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function attributes() { return $this->hasMany(AttributeItem::class)->orderBy('position'); }
}
