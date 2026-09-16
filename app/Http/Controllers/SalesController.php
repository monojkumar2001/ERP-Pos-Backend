<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\PaymentMethod;
use App\Models\SalesDocument;
use App\Models\SalesDocumentLine;
use App\Models\SalesDocumentPayment;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    use BelongsToCompany;

    protected const TYPE_META = [
        'direct_invoice' => [
            'prefix' => 'DSO',
            'label' => 'Direct invoice',
            'default_status' => 'Draft',
            'approve_status' => 'Completed',
            'stock_on_complete' => true,
            'ledger_on_complete' => true,
        ],
        'quotation' => [
            'prefix' => 'Q',
            'label' => 'Quotation',
            'default_status' => 'Draft',
            'approve_status' => 'Approved',
            'stock_on_complete' => false,
            'ledger_on_complete' => false,
        ],
        'sales_order' => [
            'prefix' => 'SO',
            'label' => 'Sales order',
            'default_status' => 'Draft',
            'approve_status' => 'Approved',
            'stock_on_complete' => false,
            'ledger_on_complete' => false,
        ],
        'delivery' => [
            'prefix' => 'DEL',
            'label' => 'Delivery',
            'default_status' => 'Delivered',
            'approve_status' => 'Delivered',
            'stock_on_complete' => true,
            'ledger_on_complete' => false,
        ],
        'invoice' => [
            'prefix' => 'INV',
            'label' => 'Invoice',
            'default_status' => 'Unpaid',
            'approve_status' => 'Unpaid',
            'stock_on_complete' => false,
            'ledger_on_complete' => true,
        ],
        'sales_return' => [
            'prefix' => 'SRT',
            'label' => 'Sales return',
            'default_status' => 'Draft',
            'approve_status' => 'Approved',
            'stock_on_complete' => true,
            'stock_direction' => 1,
            'ledger_on_complete' => true,
            'ledger_credit' => true,
        ],
        'batch_payment' => [
            'prefix' => 'SBP',
            'label' => 'Batch payment',
            'default_status' => 'Verified',
            'approve_status' => 'Verified',
            'stock_on_complete' => false,
            'ledger_on_complete' => true,
            'ledger_credit' => true,
        ],
        'sales_payment' => [
            'prefix' => 'SPY',
            'label' => 'Sales payment',
            'default_status' => 'Verified',
            'approve_status' => 'Verified',
            'stock_on_complete' => false,
            'ledger_on_complete' => true,
            'ledger_credit' => true,
        ],
        'customer_debit' => [
            'prefix' => 'CDB',
            'label' => 'Customer debit',
            'default_status' => 'Draft',
            'approve_status' => 'Approved',
            'stock_on_complete' => false,
            'ledger_on_complete' => true,
        ],
        'refund' => [
            'prefix' => 'SRF',
            'label' => 'Refund',
            'default_status' => 'Draft',
            'approve_status' => 'Refunded',
            'stock_on_complete' => false,
            'ledger_on_complete' => true,
            'ledger_debit' => true,
        ],
    ];

    // --- Direct invoices ---

    public function directInvoices(Request $request)
    {
        return $this->listDocuments($request, 'direct_invoice');
    }

    public function storeDirectInvoice(Request $request)
    {
        return $this->storeDocument($request, 'direct_invoice');
    }

    public function showDirectInvoice(Request $request, $id)
    {
        return $this->showDocument($request, 'direct_invoice', $id);
    }

    public function updateDirectInvoice(Request $request, $id)
    {
        return $this->updateDocument($request, 'direct_invoice', $id);
    }

    public function destroyDirectInvoice(Request $request, $id)
    {
        return $this->destroyDocument($request, 'direct_invoice', $id);
    }

    public function directInvoiceStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'direct_invoice', $id);
    }

    public function cancelDirectInvoice(Request $request, $id)
    {
        $doc = $this->findDocument($request, 'direct_invoice', $id);

        if ($doc->status === 'Cancelled') {
            return ApiResponse::success($this->documentPayload($doc), 'Already cancelled');
        }

        if (!in_array($doc->status, ['Draft', 'Completed'], true)) {
            return ApiResponse::error('Only draft or completed direct invoices can be cancelled.', 422);
        }

        try {
            DB::transaction(function () use ($doc) {
                if ($doc->status === 'Completed') {
                    $this->applyStockForDocument($doc, -1);
                    $this->reverseLedgerForDocument($doc);
                }
                $doc->update(['status' => 'Cancelled']);
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->documentPayload($doc->fresh($this->defaultRelations())),
            'Direct invoice cancelled'
        );
    }

    // --- Quotations ---

    public function quotations(Request $request)
    {
        return $this->listDocuments($request, 'quotation');
    }

    public function storeQuotation(Request $request)
    {
        return $this->storeDocument($request, 'quotation');
    }

    public function showQuotation(Request $request, $id)
    {
        return $this->showDocument($request, 'quotation', $id);
    }

    public function updateQuotation(Request $request, $id)
    {
        return $this->updateDocument($request, 'quotation', $id);
    }

    public function destroyQuotation(Request $request, $id)
    {
        return $this->destroyDocument($request, 'quotation', $id);
    }

    public function quotationStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'quotation', $id);
    }

    public function convertQuotationToOrder(Request $request, $id)
    {
        $quotation = $this->findDocument($request, 'quotation', $id);
        $quotation->load('lines');

        if (!in_array($quotation->status, ['Approved', 'Draft'], true)) {
            return ApiResponse::error('Only draft or approved quotations can be converted.', 422);
        }

        try {
            $order = DB::transaction(function () use ($request, $quotation) {
                if ($quotation->status === 'Draft') {
                    $quotation->update(['status' => 'Approved']);
                }

                $order = $this->cloneDocument($quotation, 'sales_order', [
                    'status' => 'Approved',
                    'order_status' => 'Approved',
                    'delivery_status' => 'Pending',
                    'invoice_status' => 'Not Invoiced',
                    'parent_id' => $quotation->id,
                    'created_by' => $request->user()?->id,
                ]);

                $quotation->update(['status' => 'Ordered']);

                return $order;
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->documentPayload($order->fresh($this->defaultRelations())),
            'Converted to sales order',
            201
        );
    }

    // --- Sales orders ---

    public function orders(Request $request)
    {
        return $this->listDocuments($request, 'sales_order');
    }

    public function storeOrder(Request $request)
    {
        return $this->storeDocument($request, 'sales_order');
    }

    public function showOrder(Request $request, $id)
    {
        return $this->showDocument($request, 'sales_order', $id);
    }

    public function updateOrder(Request $request, $id)
    {
        return $this->updateDocument($request, 'sales_order', $id);
    }

    public function destroyOrder(Request $request, $id)
    {
        return $this->destroyDocument($request, 'sales_order', $id);
    }

    public function orderStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'sales_order', $id);
    }

    public function convertOrderToDelivery(Request $request, $id)
    {
        $order = $this->findDocument($request, 'sales_order', $id);
        $order->load('lines');

        if ($order->status === 'Draft') {
            return ApiResponse::error('Approve the sales order before converting to delivery.', 422);
        }

        if ($order->delivery_status === 'Delivered') {
            return ApiResponse::error('Order is already delivered.', 422);
        }

        try {
            $delivery = DB::transaction(function () use ($request, $order) {
                $delivery = $this->cloneDocument($order, 'delivery', [
                    'status' => 'Delivered',
                    'delivery_status' => 'Delivered',
                    'parent_id' => $order->id,
                    'created_by' => $request->user()?->id,
                ]);

                $this->applyStockForDocument($delivery, 1);
                $order->update(['delivery_status' => 'Delivered']);

                return $delivery;
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->documentPayload($delivery->fresh($this->defaultRelations())),
            'Converted to delivery',
            201
        );
    }

    public function convertOrderToInvoice(Request $request, $id)
    {
        $order = $this->findDocument($request, 'sales_order', $id);
        $order->load(['lines', 'payments']);

        if ($order->status === 'Draft') {
            return ApiResponse::error('Approve the sales order before converting to invoice.', 422);
        }

        if ($order->invoice_status === 'Invoiced') {
            return ApiResponse::error('Order is already invoiced.', 422);
        }

        try {
            $invoice = DB::transaction(function () use ($request, $order) {
                $paid = (float) $order->amount_paid;
                $due = max(0, (float) $order->grand_total - $paid);
                $status = $due <= 0 ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Unpaid');

                $invoice = $this->cloneDocument($order, 'invoice', [
                    'status' => $status,
                    'amount_paid' => $paid,
                    'amount_due' => $due,
                    'parent_id' => $order->id,
                    'created_by' => $request->user()?->id,
                ]);

                $this->writeLedgerForDocument($invoice);
                $order->update(['invoice_status' => 'Invoiced']);

                return $invoice;
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->documentPayload($invoice->fresh($this->defaultRelations())),
            'Converted to invoice',
            201
        );
    }

    // --- Deliveries ---

    public function deliveries(Request $request)
    {
        return $this->listDocuments($request, 'delivery');
    }

    public function storeDelivery(Request $request)
    {
        return $this->storeDocument($request, 'delivery');
    }

    public function showDelivery(Request $request, $id)
    {
        return $this->showDocument($request, 'delivery', $id);
    }

    public function updateDelivery(Request $request, $id)
    {
        return $this->updateDocument($request, 'delivery', $id);
    }

    public function destroyDelivery(Request $request, $id)
    {
        return $this->destroyDocument($request, 'delivery', $id);
    }

    public function deliveryStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'delivery', $id);
    }

    // --- Invoices ---

    public function invoices(Request $request)
    {
        return $this->listDocuments($request, 'invoice');
    }

    public function storeInvoice(Request $request)
    {
        return $this->storeDocument($request, 'invoice');
    }

    public function showInvoice(Request $request, $id)
    {
        return $this->showDocument($request, 'invoice', $id);
    }

    public function updateInvoice(Request $request, $id)
    {
        return $this->updateDocument($request, 'invoice', $id);
    }

    public function destroyInvoice(Request $request, $id)
    {
        return $this->destroyDocument($request, 'invoice', $id);
    }

    public function invoiceStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'invoice', $id);
    }

    // --- Returns ---

    public function returns(Request $request)
    {
        return $this->listDocuments($request, 'sales_return');
    }

    public function storeReturn(Request $request)
    {
        return $this->storeDocument($request, 'sales_return');
    }

    public function showReturn(Request $request, $id)
    {
        return $this->showDocument($request, 'sales_return', $id);
    }

    public function updateReturn(Request $request, $id)
    {
        return $this->updateDocument($request, 'sales_return', $id);
    }

    public function destroyReturn(Request $request, $id)
    {
        return $this->destroyDocument($request, 'sales_return', $id);
    }

    public function returnStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'sales_return', $id);
    }

    // --- Batch payments ---

    public function batchPayments(Request $request)
    {
        return $this->listDocuments($request, 'batch_payment');
    }

    public function storeBatchPayment(Request $request)
    {
        return $this->storeDocument($request, 'batch_payment');
    }

    public function showBatchPayment(Request $request, $id)
    {
        return $this->showDocument($request, 'batch_payment', $id);
    }

    public function updateBatchPayment(Request $request, $id)
    {
        return $this->updateDocument($request, 'batch_payment', $id);
    }

    public function destroyBatchPayment(Request $request, $id)
    {
        return $this->destroyDocument($request, 'batch_payment', $id);
    }

    public function batchPaymentStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'batch_payment', $id);
    }

    // --- Sales payments ---

    public function payments(Request $request)
    {
        return $this->listDocuments($request, 'sales_payment');
    }

    public function storePayment(Request $request)
    {
        return $this->storeDocument($request, 'sales_payment');
    }

    public function showPayment(Request $request, $id)
    {
        return $this->showDocument($request, 'sales_payment', $id);
    }

    public function updatePayment(Request $request, $id)
    {
        return $this->updateDocument($request, 'sales_payment', $id);
    }

    public function destroyPayment(Request $request, $id)
    {
        return $this->destroyDocument($request, 'sales_payment', $id);
    }

    public function paymentStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'sales_payment', $id);
    }

    // --- Customer debits ---

    public function customerDebits(Request $request)
    {
        return $this->listDocuments($request, 'customer_debit');
    }

    public function storeCustomerDebit(Request $request)
    {
        return $this->storeDocument($request, 'customer_debit');
    }

    public function showCustomerDebit(Request $request, $id)
    {
        return $this->showDocument($request, 'customer_debit', $id);
    }

    public function updateCustomerDebit(Request $request, $id)
    {
        return $this->updateDocument($request, 'customer_debit', $id);
    }

    public function destroyCustomerDebit(Request $request, $id)
    {
        return $this->destroyDocument($request, 'customer_debit', $id);
    }

    public function customerDebitStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'customer_debit', $id);
    }

    // --- Refunds ---

    public function refunds(Request $request)
    {
        return $this->listDocuments($request, 'refund');
    }

    public function storeRefund(Request $request)
    {
        return $this->storeDocument($request, 'refund');
    }

    public function showRefund(Request $request, $id)
    {
        return $this->showDocument($request, 'refund', $id);
    }

    public function updateRefund(Request $request, $id)
    {
        return $this->updateDocument($request, 'refund', $id);
    }

    public function destroyRefund(Request $request, $id)
    {
        return $this->destroyDocument($request, 'refund', $id);
    }

    public function refundStatus(Request $request, $id)
    {
        return $this->updateDocumentStatus($request, 'refund', $id);
    }

    // --- Core helpers ---

    protected function listDocuments(Request $request, string $type)
    {
        $companyId = $this->requireCompany($request);

        $query = SalesDocument::forCompany($companyId)
            ->where('type', $type)
            ->with(['customer', 'creator', 'parent', 'payments.paymentMethod'])
            ->orderByDesc('document_date')
            ->orderByDesc('id');

        $sum = (clone $query)->sum('grand_total');
        $docs = $query->get();
        $rows = $docs->map(fn (SalesDocument $doc) => $this->listRow($doc))->values()->all();

        $payload = [
            'rows' => $rows,
            'totalEntries' => count($rows),
            'totalAmount' => $this->money($sum),
        ];

        if ($type === 'direct_invoice') {
            $payload['summaryCards'] = [
                ['title' => 'Sub Total Amount', 'value' => $this->money($docs->sum('subtotal'))],
                ['title' => 'Total Tax Amount', 'value' => $this->money($docs->sum('tax_amount'))],
                ['title' => 'Total Discount Amount', 'value' => $this->money($docs->sum('discount_amount'))],
                ['title' => 'Grand Total Amount', 'value' => $this->money($docs->sum('grand_total'))],
            ];
        }

        return response()->json($payload);
    }

    protected function storeDocument(Request $request, string $type)
    {
        $doc = new SalesDocument([
            'type' => $type,
            'status' => self::TYPE_META[$type]['default_status'] ?? 'Draft',
        ]);
        $this->persistDocument($request, $doc, $type, true);

        return ApiResponse::success(
            $this->documentPayload($doc->fresh($this->defaultRelations())),
            self::TYPE_META[$type]['label'] . ' created',
            201
        );
    }

    protected function showDocument(Request $request, string $type, $id)
    {
        return ApiResponse::success($this->documentPayload($this->findDocument($request, $type, $id)));
    }

    protected function updateDocument(Request $request, string $type, $id)
    {
        $doc = $this->findDocument($request, $type, $id);
        if (!$this->isEditable($doc)) {
            return ApiResponse::error('Only draft documents can be updated.', 422);
        }
        $this->persistDocument($request, $doc, $type, false);

        return ApiResponse::success(
            $this->documentPayload($doc->fresh($this->defaultRelations())),
            self::TYPE_META[$type]['label'] . ' updated'
        );
    }

    protected function destroyDocument(Request $request, string $type, $id)
    {
        $doc = $this->findDocument($request, $type, $id);
        if ($doc->status !== 'Draft') {
            return ApiResponse::error('Only draft documents can be deleted.', 422);
        }
        $doc->delete();

        return ApiResponse::success(null, self::TYPE_META[$type]['label'] . ' deleted');
    }

    protected function updateDocumentStatus(Request $request, string $type, $id)
    {
        $doc = $this->findDocument($request, $type, $id);
        $meta = self::TYPE_META[$type];
        $approveStatus = $meta['approve_status'];

        $allowed = array_unique(['Draft', $approveStatus, 'Approved', 'Completed', 'Cancelled', 'Verified', 'Refunded', 'Paid', 'Unpaid', 'Partially Paid', 'Delivered', 'Ordered']);
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in($allowed)],
        ]);

        $newStatus = $data['status'];
        if ($newStatus === 'Approved' && $approveStatus !== 'Approved') {
            $newStatus = $approveStatus;
        }

        $oldStatus = $doc->status;
        if ($oldStatus === 'Cancelled') {
            return ApiResponse::error('Cancelled documents cannot change status.', 422);
        }
        if ($newStatus === $oldStatus) {
            return ApiResponse::success($this->documentPayload($doc), 'Status unchanged');
        }

        $completedStatuses = [$approveStatus, 'Completed', 'Delivered', 'Verified', 'Refunded', 'Paid', 'Approved'];
        $wasComplete = in_array($oldStatus, $completedStatuses, true) && $oldStatus !== 'Draft';
        $willComplete = in_array($newStatus, $completedStatuses, true) && $newStatus !== 'Draft';

        try {
            DB::transaction(function () use ($doc, $type, $meta, &$newStatus, $wasComplete, $willComplete) {
                $doc->load(['lines', 'payments']);

                if ($willComplete && !$wasComplete) {
                    if ($type === 'direct_invoice') {
                        $newStatus = 'Completed';
                    }
                    if ($type === 'invoice') {
                        $paid = (float) $doc->amount_paid;
                        $due = max(0, (float) $doc->grand_total - $paid);
                        $doc->amount_due = $due;
                        $newStatus = $due <= 0 ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Unpaid');
                    }
                    if ($type === 'sales_order') {
                        $doc->order_status = 'Approved';
                        $doc->delivery_status = $doc->delivery_status ?: 'Pending';
                        $doc->invoice_status = $doc->invoice_status ?: 'Not Invoiced';
                    }

                    if (!empty($meta['stock_on_complete'])) {
                        $this->applyStockForDocument($doc, 1);
                    }
                    if (!empty($meta['ledger_on_complete'])) {
                        $this->writeLedgerForDocument($doc);
                    }
                } elseif (!$willComplete && $wasComplete && $newStatus === 'Draft') {
                    if (!empty($meta['stock_on_complete'])) {
                        $this->applyStockForDocument($doc, -1);
                    }
                    if (!empty($meta['ledger_on_complete'])) {
                        $this->reverseLedgerForDocument($doc);
                    }
                }

                $doc->status = $newStatus;
                $doc->save();
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->documentPayload($doc->fresh($this->defaultRelations())),
            'Status updated'
        );
    }

    protected function persistDocument(Request $request, SalesDocument $doc, string $type, bool $creating): void
    {
        $companyId = $this->requireCompany($request);
        $isPaymentLike = in_array($type, ['batch_payment', 'sales_payment', 'refund'], true);

        $rules = [
            'document_date' => ($creating ? 'nullable' : 'sometimes') . '|date',
            'valid_till' => 'nullable|date',
            'customer_id' => [
                $isPaymentLike || $type === 'customer_debit' ? 'required' : 'nullable',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'warehouse_id' => [
                'nullable',
                Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'parent_id' => [
                'nullable',
                Rule::exists('sales_documents', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'sales_person' => 'nullable|string|max:255',
            'price_list' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:255',
            'ecommerce_order' => 'nullable|string|max:255',
            'discount_amount' => 'nullable|numeric|min:0',
            'adjustment' => 'nullable|numeric',
            'amount_paid' => 'nullable|numeric|min:0',
            'note' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'order_status' => 'nullable|string|max:50',
            'delivery_status' => 'nullable|string|max:50',
            'invoice_status' => 'nullable|string|max:50',
            'lines' => ($isPaymentLike || in_array($type, ['batch_payment', 'customer_debit', 'refund'], true) ? 'nullable' : ($creating ? 'required' : 'sometimes')) . '|array|min:1',
            'lines.*.item_id' => [
                'required_with:lines',
                Rule::exists('items', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'lines.*.quantity' => 'required_with:lines|numeric|min:0.001',
            'lines.*.unit' => 'nullable|string|max:50',
            'lines.*.unit_price' => 'required_with:lines|numeric|min:0',
            'lines.*.discount' => 'nullable|numeric|min:0',
            'lines.*.tax' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.payment_method_id' => [
                'nullable',
                Rule::exists('payment_methods', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'payments.*.method_name' => 'nullable|string|max:255',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'payments.*.payment_date' => 'nullable|date',
            'grand_total' => 'nullable|numeric|min:0',
        ];

        $data = $request->validate($rules);

        DB::transaction(function () use ($request, $doc, $type, $creating, $companyId, $data, $isPaymentLike) {
            $user = $request->user();
            $lines = $data['lines'] ?? [];
            $payments = $data['payments'] ?? [];
            unset($data['lines'], $data['payments']);

            if ($creating) {
                $doc->company_id = $companyId;
                $doc->type = $type;
                $doc->document_number = $this->nextDocumentNumber($companyId, $type);
                $doc->created_by = $user?->id;
                if (empty($data['document_date'])) {
                    $data['document_date'] = now()->toDateString();
                }
                if (empty($data['sales_person'])) {
                    $data['sales_person'] = $user?->name ?: ($user?->email ?: null);
                }
                if ($type === 'sales_order') {
                    $data['order_status'] = $data['order_status'] ?? 'Draft';
                    $data['delivery_status'] = $data['delivery_status'] ?? 'Pending';
                    $data['invoice_status'] = $data['invoice_status'] ?? 'Not Invoiced';
                    $data['ecommerce_order'] = $data['ecommerce_order'] ?? 'N/A';
                }
            }

            $totals = $this->computeTotals($lines, $data, $isPaymentLike);
            $data = array_merge($data, $totals);

            if ($type === 'invoice' || $type === 'direct_invoice') {
                $paid = (float) ($data['amount_paid'] ?? $doc->amount_paid ?? 0);
                if (!empty($payments)) {
                    $paid = collect($payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
                }
                $data['amount_paid'] = $paid;
                $data['amount_due'] = max(0, (float) $data['grand_total'] - $paid);
            }

            if ($isPaymentLike && empty($lines)) {
                $amount = (float) ($data['grand_total'] ?? $request->input('amount', $request->input('grand_total', 0)));
                if (!empty($payments)) {
                    $amount = collect($payments)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
                }
                $data['subtotal'] = $amount;
                $data['grand_total'] = $amount;
                $data['amount_paid'] = $amount;
                $data['amount_due'] = 0;
            }

            $doc->fill($data);
            $doc->save();

            $doc->lines()->delete();
            foreach ($lines as $line) {
                $qty = (float) $line['quantity'];
                $price = (float) $line['unit_price'];
                $discount = (float) ($line['discount'] ?? 0);
                $tax = (float) ($line['tax'] ?? 0);
                $lineTotal = round(($qty * $price) - $discount + $tax, 2);

                SalesDocumentLine::create([
                    'sales_document_id' => $doc->id,
                    'item_id' => $line['item_id'],
                    'quantity' => $qty,
                    'unit' => $line['unit'] ?? null,
                    'unit_price' => $price,
                    'discount' => $discount,
                    'tax' => $tax,
                    'line_total' => $lineTotal,
                ]);
            }

            $doc->payments()->delete();
            foreach ($payments as $payment) {
                $methodName = $payment['method_name'] ?? null;
                if (!$methodName && !empty($payment['payment_method_id'])) {
                    $methodName = PaymentMethod::where('company_id', $companyId)
                        ->where('id', $payment['payment_method_id'])
                        ->value('name');
                }
                SalesDocumentPayment::create([
                    'sales_document_id' => $doc->id,
                    'payment_method_id' => $payment['payment_method_id'] ?? null,
                    'method_name' => $methodName,
                    'amount' => $payment['amount'],
                    'payment_date' => $payment['payment_date'] ?? $doc->document_date,
                ]);
            }
        });
    }

    protected function computeTotals(array $lines, array $data, bool $isPaymentLike): array
    {
        $subtotal = 0.0;
        $tax = 0.0;
        $lineDiscount = 0.0;

        foreach ($lines as $line) {
            $qty = (float) ($line['quantity'] ?? 0);
            $price = (float) ($line['unit_price'] ?? 0);
            $discount = (float) ($line['discount'] ?? 0);
            $lineTax = (float) ($line['tax'] ?? 0);
            $subtotal += ($qty * $price);
            $lineDiscount += $discount;
            $tax += $lineTax;
        }

        $headerDiscount = (float) ($data['discount_amount'] ?? $lineDiscount);
        if (!isset($data['discount_amount'])) {
            $headerDiscount = $lineDiscount;
        }
        $adjustment = (float) ($data['adjustment'] ?? 0);
        $grand = round($subtotal - $headerDiscount + $tax + $adjustment, 2);

        if ($isPaymentLike && empty($lines) && isset($data['grand_total'])) {
            $grand = (float) $data['grand_total'];
            $subtotal = $grand;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax_amount' => round($tax, 2),
            'discount_amount' => round($headerDiscount, 2),
            'adjustment' => round($adjustment, 2),
            'grand_total' => $grand,
        ];
    }

    protected function cloneDocument(SalesDocument $source, string $newType, array $overrides = []): SalesDocument
    {
        $meta = self::TYPE_META[$newType];
        $clone = $source->replicate([
            'document_number',
            'type',
            'status',
            'order_status',
            'delivery_status',
            'invoice_status',
            'parent_id',
            'created_by',
        ]);
        $clone->type = $newType;
        $clone->document_number = $this->nextDocumentNumber((int) $source->company_id, $newType);
        $clone->document_date = now()->toDateString();
        $clone->status = $overrides['status'] ?? ($meta['default_status'] ?? 'Draft');
        $clone->parent_id = $overrides['parent_id'] ?? $source->id;
        $clone->created_by = $overrides['created_by'] ?? $source->created_by;
        $clone->order_status = $overrides['order_status'] ?? null;
        $clone->delivery_status = $overrides['delivery_status'] ?? null;
        $clone->invoice_status = $overrides['invoice_status'] ?? null;
        if (array_key_exists('amount_paid', $overrides)) {
            $clone->amount_paid = $overrides['amount_paid'];
        }
        if (array_key_exists('amount_due', $overrides)) {
            $clone->amount_due = $overrides['amount_due'];
        }
        $clone->save();

        foreach ($source->lines as $line) {
            SalesDocumentLine::create([
                'sales_document_id' => $clone->id,
                'item_id' => $line->item_id,
                'quantity' => $line->quantity,
                'unit' => $line->unit,
                'unit_price' => $line->unit_price,
                'discount' => $line->discount,
                'tax' => $line->tax,
                'line_total' => $line->line_total,
            ]);
        }

        if ($source->relationLoaded('payments')) {
            foreach ($source->payments as $payment) {
                SalesDocumentPayment::create([
                    'sales_document_id' => $clone->id,
                    'payment_method_id' => $payment->payment_method_id,
                    'method_name' => $payment->method_name,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                ]);
            }
        }

        return $clone;
    }

    protected function applyStockForDocument(SalesDocument $doc, int $direction): void
    {
        $meta = self::TYPE_META[$doc->type] ?? [];
        $stockSign = (int) ($meta['stock_direction'] ?? -1); // default decrease
        $doc->loadMissing('lines');

        $warehouseId = $doc->warehouse_id;
        if (!$warehouseId) {
            $warehouseId = Warehouse::where('company_id', $doc->company_id)
                ->where('status', true)
                ->orderByDesc('is_default')
                ->value('id');
        }
        if (!$warehouseId) {
            // Fall back to adjusting item.stock only
            foreach ($doc->lines as $line) {
                $delta = $stockSign * (float) $line->quantity * $direction;
                $item = Item::where('company_id', $doc->company_id)->where('id', $line->item_id)->lockForUpdate()->first();
                if (!$item) {
                    continue;
                }
                $newQty = (float) $item->stock + $delta;
                if ($newQty < 0) {
                    throw new \RuntimeException('Insufficient stock for item: ' . ($item->name ?: $item->id));
                }
                $item->update(['stock' => $newQty]);
            }
            return;
        }

        foreach ($doc->lines as $line) {
            $delta = $stockSign * (float) $line->quantity * $direction;
            $this->adjustStockBalance((int) $doc->company_id, (int) $line->item_id, (int) $warehouseId, $delta);
        }
    }

    protected function adjustStockBalance(int $companyId, int $itemId, int $warehouseId, float $delta): void
    {
        $balance = ItemStockBalance::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('bin_location_id')
            ->lockForUpdate()
            ->first();

        if ($balance) {
            $newQty = (float) $balance->quantity + $delta;
            if ($newQty < 0) {
                throw new \RuntimeException('Insufficient stock for this operation.');
            }
            $balance->update(['quantity' => $newQty]);
        } elseif ($delta > 0) {
            ItemStockBalance::create([
                'company_id' => $companyId,
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'bin_location_id' => null,
                'quantity' => $delta,
            ]);
        } else {
            // Try reducing item.stock if no balance row exists yet
            $item = Item::where('company_id', $companyId)->where('id', $itemId)->lockForUpdate()->first();
            if (!$item || ((float) $item->stock + $delta) < 0) {
                throw new \RuntimeException('Insufficient stock for this operation.');
            }
            ItemStockBalance::create([
                'company_id' => $companyId,
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'bin_location_id' => null,
                'quantity' => (float) $item->stock + $delta,
            ]);
        }

        $total = ItemStockBalance::where('company_id', $companyId)->where('item_id', $itemId)->sum('quantity');
        Item::where('company_id', $companyId)->where('id', $itemId)->update(['stock' => $total]);
    }

    protected function writeLedgerForDocument(SalesDocument $doc): void
    {
        if (!$doc->customer_id) {
            return;
        }

        $meta = self::TYPE_META[$doc->type] ?? [];
        $note = ($meta['label'] ?? 'Sales') . ' ' . $doc->document_number;

        if (!empty($meta['ledger_credit'])) {
            $amount = (float) $doc->grand_total;
            if ($amount <= 0) {
                return;
            }
            CustomerLedgerEntry::create([
                'company_id' => $doc->company_id,
                'customer_id' => $doc->customer_id,
                'sales_document_id' => $doc->id,
                'entry_date' => $doc->document_date?->format('Y-m-d') ?? now()->toDateString(),
                'debit' => 0,
                'credit' => $amount,
                'note' => $note,
            ]);
            return;
        }

        if (!empty($meta['ledger_debit'])) {
            $amount = (float) $doc->grand_total;
            if ($amount <= 0) {
                return;
            }
            CustomerLedgerEntry::create([
                'company_id' => $doc->company_id,
                'customer_id' => $doc->customer_id,
                'sales_document_id' => $doc->id,
                'entry_date' => $doc->document_date?->format('Y-m-d') ?? now()->toDateString(),
                'debit' => $amount,
                'credit' => 0,
                'note' => $note,
            ]);
            return;
        }

        // Invoice / direct invoice: debit remaining due, credit payments
        $due = max(0, (float) $doc->grand_total - (float) $doc->amount_paid);
        $paid = (float) $doc->amount_paid;
        $date = $doc->document_date?->format('Y-m-d') ?? now()->toDateString();

        if ((float) $doc->grand_total > 0) {
            CustomerLedgerEntry::create([
                'company_id' => $doc->company_id,
                'customer_id' => $doc->customer_id,
                'sales_document_id' => $doc->id,
                'entry_date' => $date,
                'debit' => (float) $doc->grand_total,
                'credit' => 0,
                'note' => $note . ' (sale)',
            ]);
        }

        if ($paid > 0) {
            CustomerLedgerEntry::create([
                'company_id' => $doc->company_id,
                'customer_id' => $doc->customer_id,
                'sales_document_id' => $doc->id,
                'entry_date' => $date,
                'debit' => 0,
                'credit' => $paid,
                'note' => $note . ' (payment)',
            ]);
        }

        // Keep due field in sync
        $doc->amount_due = $due;
        $doc->save();
    }

    protected function reverseLedgerForDocument(SalesDocument $doc): void
    {
        CustomerLedgerEntry::where('sales_document_id', $doc->id)->delete();
    }

    protected function nextDocumentNumber(int $companyId, string $type): string
    {
        $prefix = self::TYPE_META[$type]['prefix'] ?? strtoupper(substr($type, 0, 3));
        $like = $prefix . '-%';

        $last = SalesDocument::where('company_id', $companyId)
            ->where('type', $type)
            ->where('document_number', 'like', $like)
            ->orderByDesc('id')
            ->value('document_number');

        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        if ($type === 'invoice') {
            $year = now()->format('Y');
            return sprintf('%s-%s-%06d', $prefix, $year, $seq);
        }

        return sprintf('%s-%06d', $prefix, $seq);
    }

    protected function findDocument(Request $request, string $type, $id): SalesDocument
    {
        $companyId = $this->requireCompany($request);

        return SalesDocument::forCompany($companyId)
            ->where('type', $type)
            ->with($this->defaultRelations())
            ->findOrFail($id);
    }

    protected function isEditable(SalesDocument $doc): bool
    {
        return in_array($doc->status, ['Draft', 'Unpaid'], true);
    }

    protected function defaultRelations(): array
    {
        return ['customer', 'warehouse', 'lines.item', 'payments.paymentMethod', 'parent', 'creator'];
    }

    protected function listRow(SalesDocument $doc): array
    {
        $customer = $doc->customer?->name ?: 'N/A';
        $date = $this->formatDate($doc->document_date);
        $base = ['id' => $doc->id];

        return match ($doc->type) {
            'direct_invoice' => $base + [
                'order_number' => $doc->document_number,
                'sale_date' => $date,
                'customer' => $customer,
                'sub_total' => $this->money($doc->subtotal),
                'total_tax' => $this->money($doc->tax_amount),
                'total_discount' => $this->money($doc->discount_amount),
                'grand_total' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
            'quotation' => $base + [
                'quotation_number' => $doc->document_number,
                'quotation_date' => $date,
                'valid_till_date' => $this->formatDate($doc->valid_till) ?: 'N/A',
                'customer' => $customer,
                'created_by' => $doc->creator?->name ?: ($doc->sales_person ?: 'N/A'),
                'amount' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
            'sales_order' => $base + [
                'order_number' => $doc->document_number,
                'ecommerce_order' => $doc->ecommerce_order ?: 'N/A',
                'date' => $date,
                'quotation_number' => $doc->parent && $doc->parent->type === 'quotation'
                    ? $doc->parent->document_number
                    : 'N/A',
                'customer' => $customer,
                'sub_total' => $this->money($doc->subtotal),
                'total_tax' => $this->money($doc->tax_amount),
                'grand_total' => $this->money($doc->grand_total),
                'order_status' => $doc->order_status ?: $doc->status,
                'delivery_status' => $doc->delivery_status ?: 'Pending',
                'invoice_status' => $doc->invoice_status ?: 'Not Invoiced',
            ],
            'delivery' => $base + [
                'delivery_number' => $doc->document_number,
                'delivery_date' => $date,
                'order_number' => $doc->parent?->document_number ?: 'N/A',
                'customer' => $customer,
                'delivery_type' => 'Store Pickup',
                'delivery_charge' => 'N/A',
                'delivery_channel' => 'N/A',
                'channel_charge' => $this->money(0),
                'consignment_id' => 'N/A',
                'delivery_status' => $doc->delivery_status ?: $doc->status,
                'status' => $doc->status,
            ],
            'invoice' => $base + [
                'invoice_number' => $doc->document_number,
                'invoice_no' => $doc->document_number,
                'date' => $date,
                'order_number' => $doc->parent?->document_number ?: 'N/A',
                'customer' => $customer,
                'total' => $this->money($doc->grand_total),
                'amount_paid' => $this->money($doc->amount_paid),
                'due_amount' => $this->money($doc->amount_due),
                'due' => $this->money($doc->amount_due),
                'status' => $doc->status,
            ],
            'sales_return' => $base + [
                'return_number' => $doc->document_number,
                'return_date' => $date,
                'reference' => $doc->receipt_number
                    ?: ($doc->parent && $doc->parent->type === 'invoice' ? $doc->parent->document_number : ($doc->parent?->document_number ?: 'N/A')),
                'order_number' => $this->resolveOrderNumber($doc),
                'customer' => $customer,
                'return_amount' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
            'batch_payment' => $base + [
                'batch_number' => $doc->document_number,
                'payment_number' => $doc->document_number,
                'date' => $date,
                'customer' => $customer,
                'payment_method' => $doc->payments->first()?->method_name
                    ?: ($doc->payments->first()?->paymentMethod?->name ?: 'N/A'),
                'transaction_id' => $doc->receipt_number ?: 'N/A',
                'paid_amount' => $this->money($doc->amount_paid ?: $doc->grand_total),
                'discount_amount' => $this->money($doc->discount_amount),
                'total_amount' => $this->money($doc->grand_total),
                'amount' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
            'sales_payment' => $base + [
                'payment_number' => $doc->document_number,
                'date' => $date,
                'invoice_number' => $doc->parent?->document_number
                    ?: ($doc->receipt_number ?: 'N/A'),
                'invoice_date' => $doc->parent ? $this->formatDate($doc->parent->document_date) : 'N/A',
                'customer' => $customer,
                'payment_method' => $doc->payments->first()?->method_name
                    ?: ($doc->payments->first()?->paymentMethod?->name ?: 'N/A'),
                'paid' => $this->money($doc->amount_paid ?: $doc->grand_total),
                'discount' => $this->money($doc->discount_amount),
                'total' => $this->money($doc->grand_total),
                'amount' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
            'customer_debit' => $base + [
                'credit_number' => $doc->document_number,
                'debit_number' => $doc->document_number,
                'date' => $date,
                'customer' => $customer,
                'type' => $doc->description ?: 'Manual',
                'reference' => $doc->receipt_number ?: ($doc->parent?->document_number ?: 'N/A'),
                'total_amount' => $this->money($doc->grand_total),
                'adjusted_amount' => $this->money($doc->amount_paid),
                'adjustable_amount' => $this->money($doc->amount_due ?: max(0, (float) $doc->grand_total - (float) $doc->amount_paid)),
                'status' => $doc->status,
            ],
            'refund' => $base + [
                'refund_number' => $doc->document_number,
                'date' => $date,
                'customer' => $customer,
                'payment_method' => $doc->payments->first()?->method_name
                    ?: ($doc->payments->first()?->paymentMethod?->name ?: 'N/A'),
                'refunded_by' => $doc->sales_person ?: ($doc->creator?->name ?: 'N/A'),
                'amount_refunded' => $this->money($doc->grand_total),
                'amount' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
            default => $base + [
                'document_number' => $doc->document_number,
                'date' => $date,
                'customer' => $customer,
                'grand_total' => $this->money($doc->grand_total),
                'status' => $doc->status,
            ],
        };
    }

    protected function documentPayload(SalesDocument $doc): array
    {
        $row = $this->listRow($doc);

        return array_merge($row, [
            'type' => $doc->type,
            'document_number' => $doc->document_number,
            'document_date' => $doc->document_date?->format('Y-m-d'),
            'document_date_formatted' => $this->formatDate($doc->document_date),
            'valid_till' => $doc->valid_till?->format('Y-m-d'),
            'customer_id' => $doc->customer_id,
            'warehouse_id' => $doc->warehouse_id,
            'warehouse' => $doc->warehouse?->name,
            'parent_id' => $doc->parent_id,
            'sales_person' => $doc->sales_person,
            'price_list' => $doc->price_list,
            'receipt_number' => $doc->receipt_number,
            'ecommerce_order' => $doc->ecommerce_order,
            'order_status' => $doc->order_status,
            'delivery_status' => $doc->delivery_status,
            'invoice_status' => $doc->invoice_status,
            'subtotal' => (float) $doc->subtotal,
            'subtotal_formatted' => $this->money($doc->subtotal),
            'tax_amount' => (float) $doc->tax_amount,
            'tax_amount_formatted' => $this->money($doc->tax_amount),
            'discount_amount' => (float) $doc->discount_amount,
            'discount_amount_formatted' => $this->money($doc->discount_amount),
            'adjustment' => (float) $doc->adjustment,
            'grand_total' => (float) $doc->grand_total,
            'grand_total_formatted' => $this->money($doc->grand_total),
            'amount_paid' => (float) $doc->amount_paid,
            'amount_paid_formatted' => $this->money($doc->amount_paid),
            'amount_due' => (float) $doc->amount_due,
            'amount_due_formatted' => $this->money($doc->amount_due),
            'note' => $doc->note,
            'description' => $doc->description,
            'created_by' => $doc->creator?->name,
            'created_by_id' => $doc->created_by,
            'lines' => $doc->lines->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->item?->name,
                'item_name' => $line->item?->name,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
                'unit_price' => (float) $line->unit_price,
                'unit_price_formatted' => $this->money($line->unit_price),
                'discount' => (float) $line->discount,
                'tax' => (float) $line->tax,
                'line_total' => (float) $line->line_total,
                'line_total_formatted' => $this->money($line->line_total),
            ])->values()->all(),
            'payments' => $doc->payments->map(fn ($p) => [
                'id' => $p->id,
                'payment_method_id' => $p->payment_method_id,
                'method_name' => $p->method_name ?: $p->paymentMethod?->name,
                'amount' => (float) $p->amount,
                'amount_formatted' => $this->money($p->amount),
                'payment_date' => $p->payment_date?->format('Y-m-d'),
            ])->values()->all(),
        ]);
    }

    protected function resolveOrderNumber(SalesDocument $doc): string
    {
        if ($doc->parent && $doc->parent->type === 'sales_order') {
            return $doc->parent->document_number;
        }
        if ($doc->parent && $doc->parent->parent_id) {
            $grand = SalesDocument::find($doc->parent->parent_id);
            if ($grand) {
                return $grand->document_number;
            }
        }
        return $doc->parent?->document_number ?: 'N/A';
    }

    protected function money($amount): string
    {
        $n = (float) $amount;
        $formatted = number_format(abs($n), 2, '.', ',');
        $formatted = preg_replace('/\.00$/', '', $formatted);
        $sign = $n < 0 ? '-' : '';

        return '৳ ' . $sign . $formatted;
    }

    protected function formatDate($date): string
    {
        if (!$date) {
            return '';
        }

        return Carbon::parse($date)->format('j M Y');
    }
}
