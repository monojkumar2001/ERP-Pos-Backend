<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class BinLocation extends Model
{
    use HasCompanyScope;
    protected $fillable = ['company_id', 'warehouse_id', 'name', 'code', 'status'];
    protected $casts = ['status' => 'boolean'];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
}
