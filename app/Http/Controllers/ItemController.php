<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Manufacturer;
use App\Models\Size;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Item::forCompany($this->requireCompany($request))
            ->with(['category', 'brand', 'color', 'size'])
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => $this->listRow($m));
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request)
    {
        $m = new Item;
        $this->persist($request, $m);
        return ApiResponse::success($this->payload($m), 'Item created', 201);
    }

    public function show(Request $request, $id)
    {
        return ApiResponse::success($this->payload($this->find($request, $id)));
    }

    public function update(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $this->persist($request, $m);
        return ApiResponse::success($this->payload($m), 'Item updated');
    }

    public function destroy(Request $request, $id)
    {
        $this->find($request, $id)->delete();
        return ApiResponse::success(null, 'Item deleted');
    }

    public function status(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $d = $request->validate(['status' => 'required']);
        $m->update(['status' => $this->boolStatus($d['status'])]);
        return ApiResponse::success($this->payload($m), 'Status updated');
    }

    public function openingStock(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $d = $request->validate([
            'opening_stock' => 'required|numeric|min:0',
            'stock' => 'sometimes|numeric|min:0',
        ]);
        $opening = $d['opening_stock'];
        $m->update([
            'opening_stock' => $opening,
            'stock' => $d['stock'] ?? $opening,
        ]);
        return ApiResponse::success($this->payload($m), 'Opening stock updated');
    }

    private function persist(Request $request, Item $model): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'sku' => ($model->exists ? 'sometimes' : 'required_without:code') . '|nullable|string|max:100',
            'code' => 'nullable|string|max:100',
            'category_id' => ['nullable', Rule::exists('item_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'category' => 'nullable|string|max:255',
            'categories' => 'nullable|string|max:255',
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'brand' => 'nullable|string|max:255',
            'manufacturer_id' => ['nullable', Rule::exists('manufacturers', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'manufacturer' => 'nullable|string|max:255',
            'color_id' => ['nullable', Rule::exists('colors', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'color' => 'nullable|string|max:255',
            'size_id' => ['nullable', Rule::exists('sizes', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'size' => 'nullable|string|max:255',
            'attribute_set_id' => ['nullable', Rule::exists('attribute_sets', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'price' => 'sometimes|numeric|min:0',
            'sale_price' => 'sometimes|numeric|min:0',
            'cost' => 'sometimes|numeric|min:0',
            'purchase_price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|numeric|min:0',
            'available_stock' => 'sometimes|numeric|min:0',
            'opening_stock' => 'sometimes|numeric|min:0',
            'uom' => 'nullable|string|max:100',
            'base_unit' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'type' => 'nullable|string|max:50',
            'status' => 'sometimes',
        ]);

        if (empty($data['sku']) && !empty($data['code'])) {
            $data['sku'] = $data['code'];
        }
        unset($data['code']);

        $categoryName = $data['category'] ?? $data['categories'] ?? null;
        if (!empty($categoryName) && empty($data['category_id'])) {
            $data['category_id'] = ItemCategory::forCompany($companyId)
                ->where('name', $categoryName)
                ->value('id');
        }
        unset($data['category'], $data['categories']);

        if (!empty($data['brand']) && empty($data['brand_id'])) {
            $data['brand_id'] = Brand::firstOrCreate(
                ['company_id' => $companyId, 'name' => $data['brand']],
                ['status' => true]
            )->id;
        }
        unset($data['brand']);

        if (!empty($data['manufacturer']) && empty($data['manufacturer_id'])) {
            $data['manufacturer_id'] = Manufacturer::firstOrCreate(
                ['company_id' => $companyId, 'name' => $data['manufacturer']],
                ['status' => true]
            )->id;
        }
        unset($data['manufacturer']);

        if (!empty($data['color']) && empty($data['color_id'])) {
            $data['color_id'] = Color::forCompany($companyId)
                ->where('name', $data['color'])
                ->value('id');
        }
        unset($data['color']);

        if (!empty($data['size']) && empty($data['size_id'])) {
            $data['size_id'] = Size::forCompany($companyId)
                ->where('name', $data['size'])
                ->value('id');
        }
        unset($data['size']);

        if (array_key_exists('sale_price', $data)) {
            $data['price'] = $data['sale_price'];
        }
        unset($data['sale_price']);

        if (array_key_exists('purchase_price', $data)) {
            $data['cost'] = $data['purchase_price'];
        }
        unset($data['purchase_price']);

        if (array_key_exists('available_stock', $data)) {
            $data['stock'] = $data['available_stock'];
        }
        unset($data['available_stock']);

        if (array_key_exists('base_unit', $data)) {
            $data['uom'] = $data['base_unit'];
        }
        unset($data['base_unit'], $data['type']);

        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }

        $data['company_id'] = $companyId;
        $model->fill($data)->save();
    }

    private function listRow(Item $m): array
    {
        $uom = strtolower($m->uom ?: 'pc');
        return [
            'id' => $m->id,
            'name' => $m->name,
            'code' => $m->sku,
            'categories' => optional($m->category)->name ?: 'N/A',
            'type' => 'Product',
            'brand' => optional($m->brand)->name ?: 'N/A',
            'color' => optional($m->color)->name ?: 'N/A',
            'size' => optional($m->size)->name ?: 'N/A',
            'sale_price' => '৳ ' . number_format((float) $m->price, 0),
            'available_stock' => rtrim(rtrim(number_format((float) $m->stock, 3, '.', ''), '0'), '.') . ' ' . $uom,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function payload(Item $m): array
    {
        $m->load(['category', 'brand', 'manufacturer', 'color', 'size', 'attributeSet']);
        $uom = $m->uom ?: 'pc';

        return [
            'id' => $m->id,
            'name' => $m->name,
            'item' => $m->name,
            'code' => $m->sku,
            'sku' => $m->sku,
            'category' => optional($m->category)->name ?: 'N/A',
            'categories' => optional($m->category)->name ?: 'N/A',
            'categoryId' => $m->category_id,
            'type' => 'Product',
            'brand' => optional($m->brand)->name ?: 'N/A',
            'brandId' => $m->brand_id,
            'manufacturer' => optional($m->manufacturer)->name ?: 'N/A',
            'manufacturerId' => $m->manufacturer_id,
            'color' => optional($m->color)->name ?: 'N/A',
            'colorId' => $m->color_id,
            'size' => optional($m->size)->name ?: 'N/A',
            'sizeId' => $m->size_id,
            'attributeSetId' => $m->attribute_set_id,
            'attributeSet' => optional($m->attributeSet)->name ?: 'N/A',
            'sale_price' => '৳ ' . number_format((float) $m->price, 0),
            'purchase_price' => '৳ ' . number_format((float) $m->cost, 0),
            'price' => (float) $m->price,
            'cost' => (float) $m->cost,
            'available_stock' => rtrim(rtrim(number_format((float) $m->stock, 3, '.', ''), '0'), '.') . ' ' . strtolower($uom),
            'stock' => (float) $m->stock,
            'opening_stock' => (float) $m->opening_stock,
            'base_unit' => $uom,
            'uom' => $uom,
            'description' => $m->description,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): Item
    {
        return Item::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
