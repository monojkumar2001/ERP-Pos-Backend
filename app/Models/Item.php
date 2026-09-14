<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasCompanyScope;
    protected $fillable = [
        'company_id', 'sku', 'name', 'category_id', 'brand_id', 'manufacturer_id',
        'color_id', 'size_id', 'attribute_set_id', 'price', 'cost', 'stock',
        'opening_stock', 'uom', 'description', 'status',
    ];
    protected $casts = [
        'price' => 'decimal:2', 'cost' => 'decimal:2', 'stock' => 'decimal:3',
        'opening_stock' => 'decimal:3', 'status' => 'boolean',
    ];

    public function category() { return $this->belongsTo(ItemCategory::class, 'category_id'); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function manufacturer() { return $this->belongsTo(Manufacturer::class); }
    public function color() { return $this->belongsTo(Color::class); }
    public function size() { return $this->belongsTo(Size::class); }
    public function attributeSet() { return $this->belongsTo(AttributeSet::class); }
}
