<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemStockBalance;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferLine;
use App\Models\WarehouseUserAccess;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run()
    {
        Company::query()->each(fn (Company $company) => $this->seedCompany($company));
    }

    private function seedCompany(Company $company): void
    {
        $cid = $company->id;

        $warehouse = Warehouse::where('company_id', $cid)->where('status', true)->orderByDesc('is_default')->first();
        if (!$warehouse) {
            return;
        }

        $secondWarehouse = Warehouse::where('company_id', $cid)
            ->where('id', '!=', $warehouse->id)
            ->where('status', true)
            ->first();

        if (!$secondWarehouse) {
            $secondWarehouse = Warehouse::create([
                'company_id' => $cid,
                'name' => 'Secondary Warehouse',
                'code' => 'WH-002',
                'is_default' => false,
                'status' => true,
            ]);
        }

        $items = Item::where('company_id', $cid)->where('status', true)->limit(3)->get();
        if ($items->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            ItemStockBalance::updateOrCreate(
                [
                    'company_id' => $cid,
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'bin_location_id' => null,
                ],
                ['quantity' => max(10, (float) $item->stock)]
            );
            $item->update(['low_stock' => 5]);
        }

        $this->syncItemStocks($cid);

        $admin = User::where('company_id', $cid)->orderByDesc('is_owner')->first();
        if ($admin) {
            WarehouseUserAccess::firstOrCreate([
                'company_id' => $cid,
                'user_id' => $admin->id,
                'warehouse_id' => $warehouse->id,
            ]);
        }

        if (StockAdjustment::where('company_id', $cid)->exists()) {
            return;
        }

        $reason = AdjustmentReason::where('company_id', $cid)->where('type', 'increase')->first();
        $firstItem = $items->first();

        $draft = StockAdjustment::create([
            'company_id' => $cid,
            'adjustment_number' => 'SA-' . now()->format('Y') . '-00000001',
            'adjustment_date' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'adjustment_reason_id' => $reason?->id,
            'adjustment_type' => 'Increase',
            'status' => 'Draft',
            'note' => 'Demo draft adjustment',
            'created_by' => $admin?->id,
        ]);
        StockAdjustmentLine::create([
            'stock_adjustment_id' => $draft->id,
            'item_id' => $firstItem->id,
            'quantity' => 5,
            'unit_cost' => (float) $firstItem->cost,
        ]);

        $approved = StockAdjustment::create([
            'company_id' => $cid,
            'adjustment_number' => 'SA-' . now()->format('Y') . '-00000002',
            'adjustment_date' => now()->subDay()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'adjustment_reason_id' => $reason?->id,
            'adjustment_type' => 'Increase',
            'status' => 'Approved',
            'note' => 'Demo approved adjustment',
            'created_by' => $admin?->id,
        ]);
        StockAdjustmentLine::create([
            'stock_adjustment_id' => $approved->id,
            'item_id' => $firstItem->id,
            'quantity' => 2,
            'unit_cost' => (float) $firstItem->cost,
        ]);
        ItemStockBalance::where('company_id', $cid)
            ->where('item_id', $firstItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('bin_location_id')
            ->increment('quantity', 2);
        $this->syncItemStocks($cid);

        $secondItem = $items->skip(1)->first() ?: $firstItem;
        $transfer = WarehouseTransfer::create([
            'company_id' => $cid,
            'transfer_number' => 'WT-' . now()->format('Y') . '-00000001',
            'transfer_date' => now()->toDateString(),
            'source_warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $secondWarehouse->id,
            'kind' => 'movement',
            'status' => 'Initiated',
            'note' => 'Demo in-transit transfer for receivable stock',
        ]);
        WarehouseTransferLine::create([
            'warehouse_transfer_id' => $transfer->id,
            'item_id' => $secondItem->id,
            'quantity' => 3,
            'uom' => $secondItem->uom ?: 'Piece',
        ]);
        ItemStockBalance::where('company_id', $cid)
            ->where('item_id', $secondItem->id)
            ->where('warehouse_id', $warehouse->id)
            ->whereNull('bin_location_id')
            ->decrement('quantity', 3);
        $this->syncItemStocks($cid);
    }

    private function syncItemStocks(int $companyId): void
    {
        Item::where('company_id', $companyId)->each(function (Item $item) use ($companyId) {
            $total = ItemStockBalance::where('company_id', $companyId)
                ->where('item_id', $item->id)
                ->sum('quantity');
            if ($total > 0) {
                $item->update(['stock' => $total]);
            }
        });
    }
}
