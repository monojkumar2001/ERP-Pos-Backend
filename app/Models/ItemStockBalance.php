<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class ItemStockBalance extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'item_id',
        'warehouse_id',
        'bin_location_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function binLocation()
    {
        return $this->belongsTo(BinLocation::class);
    }
}
