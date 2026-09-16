<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\PaymentMethod;
use App\Models\SalesDocument;
use App\Models\SalesDocumentLine;
use App\Models\SalesDocumentPayment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class SalesDocumentSeeder extends Seeder
{
    public function run()
    {
        $companyId = 1;

        if (SalesDocument::where('company_id', $companyId)->exists()) {
            return;
        }

        $customers = Customer::where('company_id', $companyId)->where('status', true)->limit(3)->get();
        $items = Item::where('company_id', $companyId)->where('status', true)->limit(3)->get();
        $warehouse = Warehouse::where('company_id', $companyId)->where('status', true)->orderByDesc('is_default')->first();
        $user = User::where('company_id', $companyId)->orderByDesc('is_owner')->first();
        $paymentMethod = PaymentMethod::where('company_id', $companyId)->where('status', true)->first();

        if ($customers->isEmpty() || $items->isEmpty()) {
            $this->command?->warn('SalesDocumentSeeder skipped: need customers and items for company_id=1.');
            return;
        }

        $customer1 = $customers[0];
        $customer2 = $customers[1] ?? $customers[0];
        $item1 = $items[0];
        $item2 = $items[1] ?? $items[0];

        // Ensure stock balances exist for completed sales
        if ($warehouse) {
            foreach ($items as $item) {
                ItemStockBalance::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'item_id' => $item->id,
                        'warehouse_id' => $warehouse->id,
                        'bin_location_id' => null,
                    ],
                    ['quantity' => max(50, (float) $item->stock)]
                );
                $total = ItemStockBalance::where('company_id', $companyId)->where('item_id', $item->id)->sum('quantity');
                $item->update(['stock' => $total]);
            }
        }

        $makeLine = function (SalesDocument $doc, Item $item, float $qty, float $price, float $discount = 0, float $tax = 0) {
            $lineTotal = round(($qty * $price) - $discount + $tax, 2);
            SalesDocumentLine::create([
                'sales_document_id' => $doc->id,
                'item_id' => $item->id,
                'quantity' => $qty,
                'unit' => $item->uom ?: 'pcs',
                'unit_price' => $price,
                'discount' => $discount,
                'tax' => $tax,
                'line_total' => $lineTotal,
            ]);
            return $lineTotal;
        };

        $totalsFrom = function (array $lineTotals, float $headerDiscount = 0) {
            $sub = array_sum($lineTotals);
            return [
                'subtotal' => $sub,
                'tax_amount' => 0,
                'discount_amount' => $headerDiscount,
                'adjustment' => 0,
                'grand_total' => $sub - $headerDiscount,
            ];
        };

        // 2 Completed direct invoices
        $dso1 = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'direct_invoice',
            'document_number' => 'DSO-000001',
            'document_date' => now()->subDays(2)->toDateString(),
            'customer_id' => $customer1->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Completed',
            'amount_paid' => 2300,
            'amount_due' => 0,
            'created_by' => $user?->id,
        ], $totalsFrom([2300])));
        $makeLine($dso1, $item1, 2, 1150);
        if ($paymentMethod) {
            SalesDocumentPayment::create([
                'sales_document_id' => $dso1->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 2300,
                'payment_date' => $dso1->document_date,
            ]);
        }
        $this->ledgerDebitCredit($dso1, 2300, 2300);

        $dso2 = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'direct_invoice',
            'document_number' => 'DSO-000002',
            'document_date' => now()->subDays(1)->toDateString(),
            'customer_id' => $customer2->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Completed',
            'amount_paid' => 1150,
            'amount_due' => 0,
            'created_by' => $user?->id,
        ], $totalsFrom([1150])));
        $makeLine($dso2, $item2, 1, 1150);
        $this->ledgerDebitCredit($dso2, 1150, 1150);

        // 2 quotations: Draft + Approved
        $q1 = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'quotation',
            'document_number' => 'Q-000001',
            'document_date' => now()->toDateString(),
            'valid_till' => now()->addDays(7)->toDateString(),
            'customer_id' => $customer1->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Draft',
            'created_by' => $user?->id,
        ], $totalsFrom([2200])));
        $makeLine($q1, $item1, 2, 1100);

        $q2 = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'quotation',
            'document_number' => 'Q-000002',
            'document_date' => now()->toDateString(),
            'valid_till' => now()->addDays(14)->toDateString(),
            'customer_id' => $customer2->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Approved',
            'created_by' => $user?->id,
        ], $totalsFrom([9395])));
        $makeLine($q2, $item1, 5, 1879);

        // 2 sales orders
        $so1 = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'sales_order',
            'document_number' => 'SO-000001',
            'document_date' => now()->subDay()->toDateString(),
            'customer_id' => $customer1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $q2->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Approved',
            'order_status' => 'Approved',
            'delivery_status' => 'Pending',
            'invoice_status' => 'Not Invoiced',
            'ecommerce_order' => 'N/A',
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($so1, $item1, 1, 700);

        $so2 = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'sales_order',
            'document_number' => 'SO-000002',
            'document_date' => now()->toDateString(),
            'customer_id' => $customer2->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Draft',
            'order_status' => 'Draft',
            'delivery_status' => 'Pending',
            'invoice_status' => 'Not Invoiced',
            'ecommerce_order' => 'N/A',
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($so2, $item2, 1, 700);

        // 1 delivery from so1
        $del = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'delivery',
            'document_number' => 'DEL-000001',
            'document_date' => now()->toDateString(),
            'customer_id' => $customer1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $so1->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Delivered',
            'delivery_status' => 'Delivered',
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($del, $item1, 1, 700);
        $so1->update(['delivery_status' => 'Delivered']);

        // 1 invoice from so1
        $inv = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'invoice',
            'document_number' => 'INV-' . now()->format('Y') . '-000001',
            'document_date' => now()->toDateString(),
            'customer_id' => $customer1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $so1->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Unpaid',
            'amount_paid' => 0,
            'amount_due' => 700,
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($inv, $item1, 1, 700);
        $this->ledgerDebitCredit($inv, 700, 0);
        $so1->update(['invoice_status' => 'Invoiced']);

        // 1 sales return
        $ret = SalesDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'sales_return',
            'document_number' => 'SRT-000001',
            'document_date' => now()->toDateString(),
            'customer_id' => $customer1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $inv->id,
            'receipt_number' => $inv->document_number,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Approved',
            'created_by' => $user?->id,
        ], $totalsFrom([480])));
        $makeLine($ret, $item1, 1, 480);
        CustomerLedgerEntry::create([
            'company_id' => $companyId,
            'customer_id' => $customer1->id,
            'sales_document_id' => $ret->id,
            'entry_date' => $ret->document_date,
            'debit' => 0,
            'credit' => 480,
            'note' => 'Sales return ' . $ret->document_number,
        ]);

        // 1 sales payment
        $pay = SalesDocument::create([
            'company_id' => $companyId,
            'type' => 'sales_payment',
            'document_number' => 'SPY-000001',
            'document_date' => now()->toDateString(),
            'customer_id' => $customer1->id,
            'parent_id' => $inv->id,
            'receipt_number' => $inv->document_number,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Verified',
            'subtotal' => 300,
            'grand_total' => 300,
            'amount_paid' => 300,
            'amount_due' => 0,
            'discount_amount' => 0,
            'created_by' => $user?->id,
        ]);
        if ($paymentMethod) {
            SalesDocumentPayment::create([
                'sales_document_id' => $pay->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 300,
                'payment_date' => $pay->document_date,
            ]);
        }
        CustomerLedgerEntry::create([
            'company_id' => $companyId,
            'customer_id' => $customer1->id,
            'sales_document_id' => $pay->id,
            'entry_date' => $pay->document_date,
            'debit' => 0,
            'credit' => 300,
            'note' => 'Sales payment ' . $pay->document_number,
        ]);

        // 1 batch payment
        $batch = SalesDocument::create([
            'company_id' => $companyId,
            'type' => 'batch_payment',
            'document_number' => 'SBP-000001',
            'document_date' => now()->subDays(3)->toDateString(),
            'customer_id' => $customer2->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Verified',
            'subtotal' => 2170,
            'grand_total' => 2170,
            'amount_paid' => 2170,
            'discount_amount' => 0,
            'receipt_number' => 'N/A',
            'created_by' => $user?->id,
        ]);
        if ($paymentMethod) {
            SalesDocumentPayment::create([
                'sales_document_id' => $batch->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 2170,
                'payment_date' => $batch->document_date,
            ]);
        }

        // 1 customer debit
        $debit = SalesDocument::create([
            'company_id' => $companyId,
            'type' => 'customer_debit',
            'document_number' => 'CDB-000001',
            'document_date' => now()->subDays(5)->toDateString(),
            'customer_id' => $customer1->id,
            'parent_id' => $ret->id,
            'receipt_number' => $ret->document_number,
            'description' => 'Sales Return',
            'status' => 'Approved',
            'subtotal' => 480,
            'grand_total' => 480,
            'amount_paid' => 0,
            'amount_due' => 480,
            'created_by' => $user?->id,
        ]);
        CustomerLedgerEntry::create([
            'company_id' => $companyId,
            'customer_id' => $customer1->id,
            'sales_document_id' => $debit->id,
            'entry_date' => $debit->document_date,
            'debit' => 480,
            'credit' => 0,
            'note' => 'Customer debit ' . $debit->document_number,
        ]);

        // 1 refund
        $refund = SalesDocument::create([
            'company_id' => $companyId,
            'type' => 'refund',
            'document_number' => 'SRF-000001',
            'document_date' => now()->subDays(10)->toDateString(),
            'customer_id' => $customer2->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Refunded',
            'subtotal' => 111,
            'grand_total' => 111,
            'amount_paid' => 111,
            'created_by' => $user?->id,
        ]);
        if ($paymentMethod) {
            SalesDocumentPayment::create([
                'sales_document_id' => $refund->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 111,
                'payment_date' => $refund->document_date,
            ]);
        }
        CustomerLedgerEntry::create([
            'company_id' => $companyId,
            'customer_id' => $customer2->id,
            'sales_document_id' => $refund->id,
            'entry_date' => $refund->document_date,
            'debit' => 111,
            'credit' => 0,
            'note' => 'Refund ' . $refund->document_number,
        ]);
    }

    private function ledgerDebitCredit(SalesDocument $doc, float $debit, float $credit): void
    {
        if (!$doc->customer_id) {
            return;
        }
        if ($debit > 0) {
            CustomerLedgerEntry::create([
                'company_id' => $doc->company_id,
                'customer_id' => $doc->customer_id,
                'sales_document_id' => $doc->id,
                'entry_date' => $doc->document_date,
                'debit' => $debit,
                'credit' => 0,
                'note' => $doc->document_number . ' (sale)',
            ]);
        }
        if ($credit > 0) {
            CustomerLedgerEntry::create([
                'company_id' => $doc->company_id,
                'customer_id' => $doc->customer_id,
                'sales_document_id' => $doc->id,
                'entry_date' => $doc->document_date,
                'debit' => 0,
                'credit' => $credit,
                'note' => $doc->document_number . ' (payment)',
            ]);
        }
    }
}
