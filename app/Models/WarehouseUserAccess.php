<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class WarehouseUserAccess extends Model
{
    use HasCompanyScope;

    protected $table = 'warehouse_user_access';

    protected $fillable = [
        'company_id',
        'user_id',
        'warehouse_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
