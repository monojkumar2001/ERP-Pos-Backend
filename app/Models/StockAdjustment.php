<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'adjustment_number',
        'adjustment_date',
        'warehouse_id',
        'adjustment_reason_id',
        'adjustment_type',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function adjustmentReason()
    {
        return $this->belongsTo(AdjustmentReason::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }
}
