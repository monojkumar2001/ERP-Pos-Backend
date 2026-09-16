<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class SupplierLedgerEntry extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'purchase_document_id',
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

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseDocument()
    {
        return $this->belongsTo(PurchaseDocument::class);
    }
}
