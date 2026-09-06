<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MoneyTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'reference_number',
        'entry_date',
        'entry_time',
        'description',
        'transfer_from',
        'transfer_to',
        'from_account_id',
        'to_account_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
