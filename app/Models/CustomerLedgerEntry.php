<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class CustomerLedgerEntry extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'customer_id',
        'sales_document_id',
        'entry_date',
        'debit',
        'credit',
        'note',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesDocument()
    {
        return $this->belongsTo(SalesDocument::class);
    }
}
