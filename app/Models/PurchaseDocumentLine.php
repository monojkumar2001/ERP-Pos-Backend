<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDocumentLine extends Model
{
    protected $fillable = [
        'purchase_document_id',
        'item_id',
        'quantity',
        'unit',
        'unit_price',
        'discount',
        'tax',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function document()
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_document_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
