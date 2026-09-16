<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesDocumentPayment extends Model
{
    protected $fillable = [
        'sales_document_id',
        'payment_method_id',
        'method_name',
        'amount',
        'payment_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function document()
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
