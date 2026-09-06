<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'start_date',
        'account_type',
        'debit_account',
        'credit_account',
        'debit_account_id',
        'credit_account_id',
        'amount',
        'recurring_interval',
        'last_transaction_date',
        'approval_status',
        'operational_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'last_transaction_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
