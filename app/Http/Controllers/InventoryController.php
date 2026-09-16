<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferLine;
use App\Models\WarehouseUserAccess;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use BelongsToCompany;

    // --- 91 Item stock ---

    public function itemStock(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $warehouseId = $request->query('warehouse_id');
        $statusFilter = $request->query('status');

        $hasBalances = ItemStockBalance::forCompany($companyId)->exists();

        $itemsQuery = Item::forCompany($companyId)->with(['category', 'brand']);

        if ($statusFilter !== null && $statusFilter !== '') {
            $active = in_array(strtolower((string) $statusFilter), ['active', '1', 'true'], true);
            $itemsQuery->where('status', $active);
        }

        $items = $itemsQuery->orderBy('name')->get();

        $balanceMap = [];
        if ($hasBalances) {
            $balanceQuery = ItemStockBalance::forCompany($companyId)
                ->select('item_id', DB::raw('SUM(quantity) as total_qty'))
                ->groupBy('item_id');

            if ($warehouseId) {
                $balanceQuery->where('warehouse_id', $warehouseId);
            }

            $balanceMap = $balanceQuery->pluck('total_qty', 'item_id')->all();
        }

        $rows = $items->map(function (Item $item) use ($hasBalances, $balanceMap) {
            $qty = $hasBalances
                ? (float) ($balanceMap[$item->id] ?? 0)
                : (float) $item->stock;

            return [
                'id' => $item->id,
                'item' => $item->name,
                'code' => $item->sku,
                'group' => $item->category?->name ?: 'N/A',
                'brand' => $item->brand?->name ?: 'N/A',
                'available_stock' => $this->formatQty($qty),
                'available_stock_raw' => $qty,
                'low_stock' => $item->low_stock !== null ? $this->formatQty($item->low_stock) : null,
                'low_stock_raw' => $item->low_stock !== null ? (float) $item->low_stock : null,
                'status' => $item->status ? 'Active' : 'Inactive',
            ];
        });

        return response()->json([
            'rows' => $rows->values()->all(),
            'totalEntries' => $rows->count(),
        ]);
    }

    // --- 92 Stock adjustments ---

    public function stockAdjustments(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $rows = StockAdjustment::forCompany($companyId)
            ->with(['warehouse', 'adjustmentReason'])
            ->orderByDesc('adjustment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StockAdjustment $a) => $this->adjustmentListRow($a));

        return response()->json([
            'rows' => $rows->values()->all(),
            'totalEntries' => $rows->count(),
        ]);
    }

    public function storeStockAdjustment(Request $request)
    {
        $adjustment = new StockAdjustment(['status' => 'Draft']);
        $this->persistStockAdjustment($request, $adjustment);

        return ApiResponse::success(
            $this->adjustmentPayload($adjustment->fresh(['warehouse', 'adjustmentReason', 'lines.item'])),
            'Stock adjustment created',
            201
        );
    }

    public function showStockAdjustment(Request $request, $id)
    {
        return ApiResponse::success($this->adjustmentPayload($this->findStockAdjustment($request, $id)));
    }

    public function updateStockAdjustment(Request $request, $id)
    {
        $adjustment = $this->findStockAdjustment($request, $id);
        if ($adjustment->status !== 'Draft') {
            return ApiResponse::error('Only draft adjustments can be updated.', 422);
        }
        $this->persistStockAdjustment($request, $adjustment);

        return ApiResponse::success(
            $this->adjustmentPayload($adjustment->fresh(['warehouse', 'adjustmentReason', 'lines.item'])),
            'Stock adjustment updated'
        );
    }

    public function destroyStockAdjustment(Request $request, $id)
    {
        $adjustment = $this->findStockAdjustment($request, $id);
        if ($adjustment->status !== 'Draft') {
            return ApiResponse::error('Only draft adjustments can be deleted.', 422);
        }
        $adjustment->delete();

        return ApiResponse::success(null, 'Stock adjustment deleted');
    }

    public function cancelStockAdjustment(Request $request, $id)
    {
        $adjustment = $this->findStockAdjustment($request, $id);
        if ($adjustment->status === 'Cancelled') {
            return ApiResponse::success($this->adjustmentPayload($adjustment), 'Already cancelled');
        }
        if ($adjustment->status === 'Approved') {
            return ApiResponse::error('Approved adjustments cannot be cancelled. Revert to draft first.', 422);
        }
        $adjustment->update(['status' => 'Cancelled']);

        return ApiResponse::success(
            $this->adjustmentPayload($adjustment->fresh(['warehouse', 'adjustmentReason', 'lines.item'])),
            'Stock adjustment cancelled'
        );
    }

    public function stockAdjustmentStatus(Request $request, $id)
    {
        $adjustment = $this->findStockAdjustment($request, $id);
        $data = $request->validate(['status' => 'required|in:Draft,Approved']);
        $newStatus = $data['status'];
        $oldStatus = $adjustment->status;

        if ($oldStatus === 'Cancelled') {
            return ApiResponse::error('Cancelled adjustments cannot change status.', 422);
        }

        if ($newStatus === $oldStatus) {
            return ApiResponse::success($this->adjustmentPayload($adjustment), 'Status unchanged');
        }

        try {
            DB::transaction(function () use ($adjustment, $newStatus, $oldStatus) {
                $adjustment->load('lines');
                if ($newStatus === 'Approved' && $oldStatus === 'Draft') {
                    $this->applyStockAdjustment($adjustment, 1);
                } elseif ($newStatus === 'Draft' && $oldStatus === 'Approved') {
                    $this->applyStockAdjustment($adjustment, -1);
                }
                $adjustment->update(['status' => $newStatus]);
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->adjustmentPayload($adjustment->fresh(['warehouse', 'adjustmentReason', 'lines.item'])),
            'Status updated'
        );
    }

    // --- 93 Warehouse transfers ---

    public function warehouseTransfers(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $rows = WarehouseTransfer::forCompany($companyId)
            ->with(['sourceWarehouse', 'destinationWarehouse'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (WarehouseTransfer $t) => $this->transferListRow($t));

        return response()->json([
            'rows' => $rows->values()->all(),
            'totalEntries' => $rows->count(),
        ]);
    }

    public function storeWarehouseTransfer(Request $request)
    {
        $transfer = new WarehouseTransfer([
            'status' => 'Draft',
            'kind' => 'movement',
        ]);
        $this->persistWarehouseTransfer($request, $transfer);

        return ApiResponse::success(
            $this->transferPayload($transfer->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])),
            'Warehouse transfer created',
            201
        );
    }

    public function requestWarehouseTransfer(Request $request)
    {
        $transfer = new WarehouseTransfer([
            'status' => 'Requested',
            'kind' => 'request',
        ]);
        $this->persistWarehouseTransfer($request, $transfer);

        return ApiResponse::success(
            $this->transferPayload($transfer->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])),
            'Warehouse transfer request created',
            201
        );
    }

    public function showWarehouseTransfer(Request $request, $id)
    {
        return ApiResponse::success($this->transferPayload($this->findWarehouseTransfer($request, $id)));
    }

    public function updateWarehouseTransfer(Request $request, $id)
    {
        $transfer = $this->findWarehouseTransfer($request, $id);
        if (!in_array($transfer->status, ['Draft', 'Requested'], true)) {
            return ApiResponse::error('Only draft or requested transfers can be updated.', 422);
        }
        $this->persistWarehouseTransfer($request, $transfer);

        return ApiResponse::success(
            $this->transferPayload($transfer->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])),
            'Warehouse transfer updated'
        );
    }

    public function initiateWarehouseTransfer(Request $request, $id)
    {
        $transfer = $this->findWarehouseTransfer($request, $id);
        if (!in_array($transfer->status, ['Draft', 'Requested'], true)) {
            return ApiResponse::error('Only draft or requested transfers can be initiated.', 422);
        }

        $transfer->load('lines.item');
        if ($transfer->lines->isEmpty()) {
            return ApiResponse::error('Transfer must have at least one line.', 422);
        }

        try {
            DB::transaction(function () use ($transfer) {
                foreach ($transfer->lines as $line) {
                    $this->adjustStockBalance(
                        $transfer->company_id,
                        $line->item_id,
                        $transfer->source_warehouse_id,
                        $line->source_bin_location_id,
                        -(float) $line->quantity
                    );
                }
                $transfer->update(['status' => 'Initiated']);
            });
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $this->transferPayload($transfer->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])),
            'Transfer initiated'
        );
    }

    public function receiveWarehouseTransfer(Request $request, $id)
    {
        $transfer = $this->findWarehouseTransfer($request, $id);
        if ($transfer->status !== 'Initiated') {
            return ApiResponse::error('Only initiated transfers can be received.', 422);
        }

        $data = $request->validate([
            'destination_bin_location_id' => [
                'nullable',
                Rule::exists('bin_locations', 'id')->where(fn ($q) => $q->where('company_id', $transfer->company_id)),
            ],
            'lines' => 'nullable|array',
            'lines.*.id' => 'required_with:lines|integer',
            'lines.*.destination_bin_location_id' => [
                'nullable',
                Rule::exists('bin_locations', 'id')->where(fn ($q) => $q->where('company_id', $transfer->company_id)),
            ],
        ]);

        $transfer->load('lines.item');
        $headerBinId = $data['destination_bin_location_id'] ?? null;
        $lineBinMap = collect($data['lines'] ?? [])->pluck('destination_bin_location_id', 'id')->all();

        DB::transaction(function () use ($transfer, $headerBinId, $lineBinMap) {
            foreach ($transfer->lines as $line) {
                $destBinId = $lineBinMap[$line->id] ?? $line->destination_bin_location_id ?? $headerBinId;
                if ($destBinId) {
                    $line->update(['destination_bin_location_id' => $destBinId]);
                }
                $this->adjustStockBalance(
                    $transfer->company_id,
                    $line->item_id,
                    $transfer->destination_warehouse_id,
                    $destBinId,
                    (float) $line->quantity
                );
            }
            $transfer->update(['status' => 'Received']);
        });

        return ApiResponse::success(
            $this->transferPayload($transfer->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])),
            'Transfer received'
        );
    }

    public function cancelWarehouseTransfer(Request $request, $id)
    {
        $transfer = $this->findWarehouseTransfer($request, $id);
        if ($transfer->status === 'Cancelled') {
            return ApiResponse::success($this->transferPayload($transfer), 'Already cancelled');
        }
        if ($transfer->status === 'Received') {
            return ApiResponse::error('Received transfers cannot be cancelled.', 422);
        }

        if ($transfer->status === 'Initiated') {
            DB::transaction(function () use ($transfer) {
                $transfer->load('lines');
                foreach ($transfer->lines as $line) {
                    $this->adjustStockBalance(
                        $transfer->company_id,
                        $line->item_id,
                        $transfer->source_warehouse_id,
                        $line->source_bin_location_id,
                        (float) $line->quantity
                    );
                }
                $transfer->update(['status' => 'Cancelled']);
            });
        } else {
            $transfer->update(['status' => 'Cancelled']);
        }

        return ApiResponse::success(
            $this->transferPayload($transfer->fresh(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])),
            'Transfer cancelled'
        );
    }

    // --- 94 Receivable stock ---

    public function receivableStock(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $rows = WarehouseTransfer::forCompany($companyId)
            ->where('status', 'Initiated')
            ->with(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (WarehouseTransfer $t) => array_merge($this->transferListRow($t), [
                'lines' => $t->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item' => $line->item?->name,
                    'quantity' => (float) $line->quantity,
                    'quantity_formatted' => $this->formatQty($line->quantity),
                    'uom' => $line->uom,
                    'source_bin_location_id' => $line->source_bin_location_id,
                    'destination_bin_location_id' => $line->destination_bin_location_id,
                ])->values()->all(),
            ]));

        return response()->json([
            'rows' => $rows->values()->all(),
            'totalEntries' => $rows->count(),
        ]);
    }

    // --- 95 Warehouse access ---

    public function warehouseAccess(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $query = WarehouseUserAccess::forCompany($companyId)->with(['user', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->query('warehouse_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $rows = $query->orderBy('warehouse_id')->orderBy('user_id')->get()->map(fn (WarehouseUserAccess $a) => [
            'id' => $a->id,
            'user_id' => $a->user_id,
            'user' => $a->user?->email ?: $a->user?->name,
            'user_email' => $a->user?->email ?: $a->user?->name,
            'warehouse_id' => $a->warehouse_id,
            'warehouse' => $a->warehouse?->name,
        ]);

        return response()->json([
            'rows' => $rows->values()->all(),
            'totalEntries' => $rows->count(),
        ]);
    }

    public function storeWarehouseAccess(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
        ]);

        $access = WarehouseUserAccess::firstOrCreate([
            'company_id' => $companyId,
            'user_id' => $data['user_id'],
            'warehouse_id' => $data['warehouse_id'],
        ]);

        $access->load(['user', 'warehouse']);

        return ApiResponse::success([
            'id' => $access->id,
            'user_id' => $access->user_id,
            'user' => $access->user?->email ?: $access->user?->name,
            'user_email' => $access->user?->email ?: $access->user?->name,
            'warehouse_id' => $access->warehouse_id,
            'warehouse' => $access->warehouse?->name,
        ], 'Warehouse access assigned', $access->wasRecentlyCreated ? 201 : 200);
    }

    public function destroyWarehouseAccess(Request $request, $id)
    {
        $access = WarehouseUserAccess::forCompany($this->requireCompany($request))->findOrFail($id);
        $access->delete();

        return ApiResponse::success(null, 'Warehouse access removed');
    }

    // --- Helpers ---

    protected function formatDate($date): string
    {
        if (!$date) {
            return '';
        }

        return Carbon::parse($date)->format('j M Y');
    }

    protected function formatQty($qty): string
    {
        $n = (float) $qty;
        $formatted = number_format(abs($n), 3, '.', ',');
        $formatted = preg_replace('/\.?0+$/', '', $formatted);

        return ($n < 0 ? '-' : '') . $formatted;
    }

    protected function nextAdjustmentNumber(int $companyId): string
    {
        return $this->nextDocReference($companyId, StockAdjustment::class, 'adjustment_number', 'SA');
    }

    protected function nextTransferNumber(int $companyId): string
    {
        return $this->nextDocReference($companyId, WarehouseTransfer::class, 'transfer_number', 'WT');
    }

    protected function nextDocReference(int $companyId, string $modelClass, string $column, string $code): string
    {
        $year = now()->format('Y');
        $prefix = "{$code}-{$year}-";
        $last = $modelClass::where('company_id', $companyId)
            ->where($column, 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value($column);
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix . str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }

    protected function findStockAdjustment(Request $request, $id): StockAdjustment
    {
        return StockAdjustment::forCompany($this->requireCompany($request))
            ->with(['warehouse', 'adjustmentReason', 'lines.item'])
            ->findOrFail($id);
    }

    protected function findWarehouseTransfer(Request $request, $id): WarehouseTransfer
    {
        return WarehouseTransfer::forCompany($this->requireCompany($request))
            ->with(['sourceWarehouse', 'destinationWarehouse', 'lines.item'])
            ->findOrFail($id);
    }

    protected function persistStockAdjustment(Request $request, StockAdjustment $adjustment): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'adjustment_number' => 'nullable|string|max:100',
            'adjustment_date' => 'required|date',
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'adjustment_reason_id' => ['nullable', Rule::exists('adjustment_reasons', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'adjustment_type' => 'required|in:Increase,Decrease',
            'note' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'lines.*.bin_location_id' => ['nullable', Rule::exists('bin_locations', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($companyId, $data, $adjustment, $request) {
            if (!$adjustment->exists) {
                $data['company_id'] = $companyId;
                $data['created_by'] = $request->user()?->id;
                if (empty($data['adjustment_number'])) {
                    $data['adjustment_number'] = $this->nextAdjustmentNumber($companyId);
                }
                if (!isset($data['status'])) {
                    $data['status'] = 'Draft';
                }
            }

            $lines = $data['lines'];
            unset($data['lines']);

            $adjustment->fill($data);
            $adjustment->save();

            $adjustment->lines()->delete();
            foreach ($lines as $line) {
                StockAdjustmentLine::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'item_id' => $line['item_id'],
                    'bin_location_id' => $line['bin_location_id'] ?? null,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);
            }
        });
    }

    protected function persistWarehouseTransfer(Request $request, WarehouseTransfer $transfer): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'transfer_number' => 'nullable|string|max:100',
            'transfer_date' => 'required|date',
            'source_warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'destination_warehouse_id' => ['required', 'different:source_warehouse_id', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'kind' => 'sometimes|in:movement,request',
            'note' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => ['required', Rule::exists('items', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'lines.*.source_bin_location_id' => ['nullable', Rule::exists('bin_locations', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'lines.*.destination_bin_location_id' => ['nullable', Rule::exists('bin_locations', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'lines.*.quantity' => 'required|numeric|min:0.001',
            'lines.*.uom' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($companyId, $data, $transfer) {
            if (!$transfer->exists) {
                $data['company_id'] = $companyId;
                if (empty($data['transfer_number'])) {
                    $data['transfer_number'] = $this->nextTransferNumber($companyId);
                }
                if (!isset($data['status'])) {
                    $data['status'] = $transfer->status ?? 'Draft';
                }
                if (!isset($data['kind'])) {
                    $data['kind'] = $transfer->kind ?? 'movement';
                }
            }

            $lines = $data['lines'];
            unset($data['lines']);

            $transfer->fill($data);
            $transfer->save();

            $transfer->lines()->delete();
            foreach ($lines as $line) {
                WarehouseTransferLine::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'item_id' => $line['item_id'],
                    'source_bin_location_id' => $line['source_bin_location_id'] ?? null,
                    'destination_bin_location_id' => $line['destination_bin_location_id'] ?? null,
                    'quantity' => $line['quantity'],
                    'uom' => $line['uom'] ?? null,
                ]);
            }
        });
    }

    protected function applyStockAdjustment(StockAdjustment $adjustment, int $direction): void
    {
        $sign = $adjustment->adjustment_type === 'Increase' ? 1 : -1;

        foreach ($adjustment->lines as $line) {
            $delta = $sign * (float) $line->quantity * $direction;
            $this->adjustStockBalance(
                $adjustment->company_id,
                $line->item_id,
                $adjustment->warehouse_id,
                $line->bin_location_id,
                $delta
            );
        }
    }

    protected function adjustStockBalance(int $companyId, int $itemId, int $warehouseId, ?int $binLocationId, float $delta): void
    {
        $query = ItemStockBalance::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId);

        if ($binLocationId === null) {
            $query->whereNull('bin_location_id');
        } else {
            $query->where('bin_location_id', $binLocationId);
        }

        $balance = $query->lockForUpdate()->first();

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
                'bin_location_id' => $binLocationId,
                'quantity' => $delta,
            ]);
        } else {
            throw new \RuntimeException('Insufficient stock for this operation.');
        }

        $this->syncItemStock($companyId, $itemId);
    }

    protected function syncItemStock(int $companyId, int $itemId): void
    {
        $total = ItemStockBalance::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->sum('quantity');

        Item::where('company_id', $companyId)->where('id', $itemId)->update(['stock' => $total]);
    }

    protected function adjustmentListRow(StockAdjustment $a): array
    {
        return [
            'id' => $a->id,
            'adjustment_number' => $a->adjustment_number,
            'date' => $this->formatDate($a->adjustment_date),
            'adjustment_reason' => $a->adjustmentReason?->name ?: 'N/A',
            'warehouse' => $a->warehouse?->name ?: 'N/A',
            'adjustment_type' => $a->adjustment_type,
            'status' => $a->status,
        ];
    }

    protected function adjustmentPayload(StockAdjustment $a): array
    {
        return [
            'id' => $a->id,
            'adjustment_number' => $a->adjustment_number,
            'adjustment_date' => $a->adjustment_date?->format('Y-m-d'),
            'adjustment_date_formatted' => $this->formatDate($a->adjustment_date),
            'warehouse_id' => $a->warehouse_id,
            'warehouse' => $a->warehouse?->name,
            'adjustment_reason_id' => $a->adjustment_reason_id,
            'adjustment_reason' => $a->adjustmentReason?->name,
            'adjustment_type' => $a->adjustment_type,
            'status' => $a->status,
            'note' => $a->note,
            'created_by' => $a->created_by,
            'lines' => $a->lines->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->item?->name,
                'item_name' => $line->item?->name,
                'bin_location_id' => $line->bin_location_id,
                'quantity' => (float) $line->quantity,
                'quantity_formatted' => $this->formatQty($line->quantity),
                'unit_cost' => $line->unit_cost !== null ? (float) $line->unit_cost : null,
            ])->values()->all(),
        ];
    }

    protected function transferListRow(WarehouseTransfer $t): array
    {
        return [
            'id' => $t->id,
            'transfer_number' => $t->transfer_number,
            'transfer_date' => $this->formatDate($t->transfer_date),
            'transfer_date_raw' => $t->transfer_date?->format('Y-m-d'),
            'source_warehouse' => $t->sourceWarehouse?->name ?: 'N/A',
            'source_warehouse_id' => $t->source_warehouse_id,
            'destination_warehouse' => $t->destinationWarehouse?->name ?: 'N/A',
            'destination_warehouse_id' => $t->destination_warehouse_id,
            'kind' => $t->kind,
            'status' => $t->status,
        ];
    }

    protected function transferPayload(WarehouseTransfer $t): array
    {
        return array_merge($this->transferListRow($t), [
            'note' => $t->note,
            'lines' => $t->lines->map(fn ($line) => [
                'id' => $line->id,
                'item_id' => $line->item_id,
                'item' => $line->item?->name,
                'source_bin_location_id' => $line->source_bin_location_id,
                'destination_bin_location_id' => $line->destination_bin_location_id,
                'quantity' => (float) $line->quantity,
                'quantity_formatted' => $this->formatQty($line->quantity),
                'uom' => $line->uom,
            ])->values()->all(),
        ]);
    }
}
