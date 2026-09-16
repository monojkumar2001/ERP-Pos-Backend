<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class WarehouseTransfer extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'transfer_number',
        'transfer_date',
        'source_warehouse_id',
        'destination_warehouse_id',
        'kind',
        'status',
        'note',
    ];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    public function sourceWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function lines()
    {
        return $this->hasMany(WarehouseTransferLine::class);
    }
}
