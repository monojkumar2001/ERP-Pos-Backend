<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class AttributeItem extends Model
{
    use HasCompanyScope;
    protected $table = 'attributes';
    protected $fillable = ['company_id', 'attribute_set_id', 'name', 'position', 'status'];
    protected $casts = ['status' => 'boolean', 'position' => 'integer'];

    public function attributeSet() { return $this->belongsTo(AttributeSet::class); }
}
