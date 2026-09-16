<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\PaymentMethod;
use App\Models\PurchaseDocument;
use App\Models\PurchaseDocumentLine;
use App\Models\PurchaseDocumentPayment;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class PurchaseDocumentSeeder extends Seeder
{
    public function run()
    {
        $companyId = 1;

        if (PurchaseDocument::where('company_id', $companyId)->exists()) {
            return;
        }

        $suppliers = Supplier::where('company_id', $companyId)->where('status', true)->limit(3)->get();
        $items = Item::where('company_id', $companyId)->where('status', true)->limit(3)->get();
        $warehouse = Warehouse::where('company_id', $companyId)->where('status', true)->orderByDesc('is_default')->first();
        $user = User::where('company_id', $companyId)->orderByDesc('is_owner')->first();
        $paymentMethod = PaymentMethod::where('company_id', $companyId)->where('status', true)->first();

        if ($suppliers->isEmpty() || $items->isEmpty()) {
            $this->command?->warn('PurchaseDocumentSeeder skipped: need suppliers and items for company_id=1.');
            return;
        }

        $supplier1 = $suppliers[0];
        $supplier2 = $suppliers[1] ?? $suppliers[0];
        $item1 = $items[0];
        $item2 = $items[1] ?? $items[0];

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

        $makeLine = function (PurchaseDocument $doc, Item $item, float $qty, float $price, float $discount = 0, float $tax = 0) {
            $lineTotal = round(($qty * $price) - $discount + $tax, 2);
            PurchaseDocumentLine::create([
                'purchase_document_id' => $doc->id,
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

        // 2 Completed direct bills
        $dp1 = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'direct_bill',
            'document_number' => 'DP-000001',
            'document_date' => now()->subDays(2)->toDateString(),
            'supplier_id' => $supplier1->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'receipt_number' => 'N/A',
            'status' => 'Completed',
            'amount_paid' => 2300,
            'amount_due' => 0,
            'created_by' => $user?->id,
        ], $totalsFrom([2300])));
        $makeLine($dp1, $item1, 2, 1150);
        if ($paymentMethod) {
            PurchaseDocumentPayment::create([
                'purchase_document_id' => $dp1->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 2300,
                'payment_date' => $dp1->document_date,
            ]);
        }
        $this->ledgerCreditDebit($dp1, 2300, 2300);

        $dp2 = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'direct_bill',
            'document_number' => 'DP-000002',
            'document_date' => now()->subDays(1)->toDateString(),
            'supplier_id' => $supplier2->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'receipt_number' => 'N/A',
            'status' => 'Completed',
            'amount_paid' => 1150,
            'amount_due' => 0,
            'created_by' => $user?->id,
        ], $totalsFrom([1150])));
        $makeLine($dp2, $item2, 1, 1150);
        $this->ledgerCreditDebit($dp2, 1150, 1150);

        // 2 requisitions: Draft + Approved
        $r1 = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'requisition',
            'document_number' => 'R-000001',
            'document_date' => now()->toDateString(),
            'valid_till' => now()->addDays(7)->toDateString(),
            'supplier_id' => $supplier1->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Draft',
            'created_by' => $user?->id,
        ], $totalsFrom([2200])));
        $makeLine($r1, $item1, 2, 1100);

        $r2 = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'requisition',
            'document_number' => 'R-000002',
            'document_date' => now()->toDateString(),
            'valid_till' => now()->addDays(14)->toDateString(),
            'supplier_id' => $supplier2->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Approved',
            'created_by' => $user?->id,
        ], $totalsFrom([9395])));
        $makeLine($r2, $item1, 5, 1879);

        // 2 purchase orders
        $po1 = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'purchase_order',
            'document_number' => 'PO-000001',
            'document_date' => now()->subDay()->toDateString(),
            'supplier_id' => $supplier1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $r2->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Approved',
            'order_status' => 'Approved',
            'delivery_status' => 'Pending',
            'invoice_status' => 'Not Billed',
            'ecommerce_order' => 'N/A',
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($po1, $item1, 1, 700);

        $po2 = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'purchase_order',
            'document_number' => 'PO-000002',
            'document_date' => now()->toDateString(),
            'supplier_id' => $supplier2->id,
            'warehouse_id' => $warehouse?->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Draft',
            'order_status' => 'Draft',
            'delivery_status' => 'Pending',
            'invoice_status' => 'Not Billed',
            'ecommerce_order' => 'N/A',
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($po2, $item2, 1, 700);

        // 1 receipt from po1
        $grn = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'receipt',
            'document_number' => 'GRN-000001',
            'document_date' => now()->toDateString(),
            'supplier_id' => $supplier1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $po1->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Received',
            'delivery_status' => 'Received',
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($grn, $item1, 1, 700);
        $po1->update(['delivery_status' => 'Received']);

        // 1 bill from po1
        $bill = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'bill',
            'document_number' => 'BL-' . now()->format('Y') . '-000001',
            'document_date' => now()->toDateString(),
            'supplier_id' => $supplier1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $po1->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Unpaid',
            'amount_paid' => 0,
            'amount_due' => 700,
            'created_by' => $user?->id,
        ], $totalsFrom([700])));
        $makeLine($bill, $item1, 1, 700);
        $this->ledgerCreditDebit($bill, 700, 0);
        $po1->update(['invoice_status' => 'Billed']);

        // 1 purchase return
        $ret = PurchaseDocument::create(array_merge([
            'company_id' => $companyId,
            'type' => 'purchase_return',
            'document_number' => 'PR-000001',
            'document_date' => now()->toDateString(),
            'supplier_id' => $supplier1->id,
            'warehouse_id' => $warehouse?->id,
            'parent_id' => $bill->id,
            'receipt_number' => $bill->document_number,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Approved',
            'created_by' => $user?->id,
        ], $totalsFrom([480])));
        $makeLine($ret, $item1, 1, 480);
        SupplierLedgerEntry::create([
            'company_id' => $companyId,
            'supplier_id' => $supplier1->id,
            'purchase_document_id' => $ret->id,
            'entry_date' => $ret->document_date,
            'debit' => 480,
            'credit' => 0,
            'note' => 'Purchase return ' . $ret->document_number,
        ]);

        // 1 purchase payment
        $pay = PurchaseDocument::create([
            'company_id' => $companyId,
            'type' => 'purchase_payment',
            'document_number' => 'PP-000001',
            'document_date' => now()->toDateString(),
            'supplier_id' => $supplier1->id,
            'parent_id' => $bill->id,
            'receipt_number' => $bill->document_number,
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
            PurchaseDocumentPayment::create([
                'purchase_document_id' => $pay->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 300,
                'payment_date' => $pay->document_date,
            ]);
        }
        SupplierLedgerEntry::create([
            'company_id' => $companyId,
            'supplier_id' => $supplier1->id,
            'purchase_document_id' => $pay->id,
            'entry_date' => $pay->document_date,
            'debit' => 300,
            'credit' => 0,
            'note' => 'Purchase payment ' . $pay->document_number,
        ]);

        // 1 batch payment
        $batch = PurchaseDocument::create([
            'company_id' => $companyId,
            'type' => 'batch_payment',
            'document_number' => 'B-000001',
            'document_date' => now()->subDays(3)->toDateString(),
            'supplier_id' => $supplier2->id,
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
            PurchaseDocumentPayment::create([
                'purchase_document_id' => $batch->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 2170,
                'payment_date' => $batch->document_date,
            ]);
        }

        // 1 supplier credit
        $credit = PurchaseDocument::create([
            'company_id' => $companyId,
            'type' => 'supplier_credit',
            'document_number' => 'DM-000001',
            'document_date' => now()->subDays(5)->toDateString(),
            'supplier_id' => $supplier1->id,
            'parent_id' => $ret->id,
            'receipt_number' => $ret->document_number,
            'description' => 'Purchase Return',
            'status' => 'Approved',
            'subtotal' => 480,
            'grand_total' => 480,
            'amount_paid' => 0,
            'amount_due' => 480,
            'created_by' => $user?->id,
        ]);
        SupplierLedgerEntry::create([
            'company_id' => $companyId,
            'supplier_id' => $supplier1->id,
            'purchase_document_id' => $credit->id,
            'entry_date' => $credit->document_date,
            'debit' => 0,
            'credit' => 480,
            'note' => 'Supplier credit ' . $credit->document_number,
        ]);

        // 1 refund
        $refund = PurchaseDocument::create([
            'company_id' => $companyId,
            'type' => 'refund',
            'document_number' => 'PRF-000001',
            'document_date' => now()->subDays(10)->toDateString(),
            'supplier_id' => $supplier2->id,
            'sales_person' => $user?->name ?: 'Demo',
            'status' => 'Refunded',
            'subtotal' => 111,
            'grand_total' => 111,
            'amount_paid' => 111,
            'created_by' => $user?->id,
        ]);
        if ($paymentMethod) {
            PurchaseDocumentPayment::create([
                'purchase_document_id' => $refund->id,
                'payment_method_id' => $paymentMethod->id,
                'method_name' => $paymentMethod->name,
                'amount' => 111,
                'payment_date' => $refund->document_date,
            ]);
        }
        SupplierLedgerEntry::create([
            'company_id' => $companyId,
            'supplier_id' => $supplier2->id,
            'purchase_document_id' => $refund->id,
            'entry_date' => $refund->document_date,
            'debit' => 0,
            'credit' => 111,
            'note' => 'Refund ' . $refund->document_number,
        ]);
    }

    private function ledgerCreditDebit(PurchaseDocument $doc, float $credit, float $debit): void
    {
        if (!$doc->supplier_id) {
            return;
        }
        if ($credit > 0) {
            SupplierLedgerEntry::create([
                'company_id' => $doc->company_id,
                'supplier_id' => $doc->supplier_id,
                'purchase_document_id' => $doc->id,
                'entry_date' => $doc->document_date,
                'debit' => 0,
                'credit' => $credit,
                'note' => $doc->document_number . ' (purchase)',
            ]);
        }
        if ($debit > 0) {
            SupplierLedgerEntry::create([
                'company_id' => $doc->company_id,
                'supplier_id' => $doc->supplier_id,
                'purchase_document_id' => $doc->id,
                'entry_date' => $doc->document_date,
                'debit' => $debit,
                'credit' => 0,
                'note' => $doc->document_number . ' (payment)',
            ]);
        }
    }
}
