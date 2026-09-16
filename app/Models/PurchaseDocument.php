<?php

namespace App\Models;

use App\Models\Concerns\HasCompanyScope;
use Illuminate\Database\Eloquent\Model;

class PurchaseDocument extends Model
{
    use HasCompanyScope;

    protected $fillable = [
        'company_id',
        'type',
        'document_number',
        'document_date',
        'valid_till',
        'supplier_id',
        'warehouse_id',
        'parent_id',
        'sales_person',
        'price_list',
        'receipt_number',
        'status',
        'order_status',
        'delivery_status',
        'invoice_status',
        'ecommerce_order',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'adjustment',
        'grand_total',
        'amount_paid',
        'amount_due',
        'note',
        'description',
        'created_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'valid_till' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'adjustment' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_due' => 'decimal:2',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(PurchaseDocumentLine::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchaseDocumentPayment::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }
}
