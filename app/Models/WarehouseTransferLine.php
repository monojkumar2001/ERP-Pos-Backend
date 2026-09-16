<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseTransferLine extends Model
{
    protected $fillable = [
        'warehouse_transfer_id',
        'item_id',
        'source_bin_location_id',
        'destination_bin_location_id',
        'quantity',
        'uom',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function warehouseTransfer()
    {
        return $this->belongsTo(WarehouseTransfer::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function sourceBinLocation()
    {
        return $this->belongsTo(BinLocation::class, 'source_bin_location_id');
    }

    public function destinationBinLocation()
    {
        return $this->belongsTo(BinLocation::class, 'destination_bin_location_id');
    }
}
