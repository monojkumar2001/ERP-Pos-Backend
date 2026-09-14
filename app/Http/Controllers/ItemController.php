<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Item;
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
            ->orderByDesc('id')
            ->get()
            ->map(function (Item $item) {
                $stock = (float) $item->stock;
                $uom = $item->uom ?: 'pc';
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->sku,
                    'sku' => $item->sku,
                    'categories' => optional($item->category)->name ?: 'N/A',
                    'category' => optional($item->category)->name ?: 'N/A',
                    'type' => 'Product',
                    'brand' => optional($item->brand)->name ?: 'N/A',
                    'color' => optional($item->color)->name ?: 'N/A',
                    'size' => optional($item->size)->name ?: 'N/A',
                    'sale_price' => '৳ ' . number_format((float) $item->price, 2),
                    'price' => (float) $item->price,
                    'available_stock' => rtrim(rtrim(number_format($stock, 3, '.', ''), '0'), '.') . ' ' . $uom,
                    'stock' => $stock,
                    'status' => $item->status ? 'Active' : 'Inactive',
                ];
            });
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request)
    {
        $item = new Item;
        $this->persist($request, $item);
        return ApiResponse::success($this->payload($item->fresh()), 'Item created', 201);
    }

    public function show(Request $request, $id)
    {
        return ApiResponse::success($this->payload($this->find($request, $id)));
    }

    public function update(Request $request, $id)
    {
        $item = $this->find($request, $id);
        $this->persist($request, $item);
        return ApiResponse::success($this->payload($item->fresh()), 'Item updated');
    }

    public function destroy(Request $request, $id)
    {
        $this->find($request, $id)->delete();
        return ApiResponse::success(null, 'Item deleted');
    }

    public function status(Request $request, $id)
    {
        $item = $this->find($request, $id);
        $data = $request->validate(['status' => 'required']);
        $item->update(['status' => $this->boolStatus($data['status'])]);
        return ApiResponse::success($this->payload($item), 'Item status updated');
    }

    public function openingStock(Request $request, $id)
    {
        $item = $this->find($request, $id);
        $data = $request->validate(['opening_stock' => 'required|numeric', 'stock' => 'sometimes|numeric']);
        $item->update(['opening_stock' => $data['opening_stock'], 'stock' => $data['stock'] ?? $data['opening_stock']]);
        return ApiResponse::success($this->payload($item), 'Opening stock updated');
    }

    private function persist(Request $request, Item $item): void
    {
        $companyId = $this->requireCompany($request);
        $required = $item->exists ? 'sometimes' : 'required';
        $data = $request->validate([
            'sku' => [$required, 'string', 'max:255', Rule::unique('items', 'sku')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($item->id)],
            'name' => "$required|string|max:255",
            'category_id' => ['nullable', $this->companyExists('item_categories', $companyId)],
            'brand_id' => ['nullable', $this->companyExists('brands', $companyId)],
            'manufacturer_id' => ['nullable', $this->companyExists('manufacturers', $companyId)],
            'color_id' => ['nullable', $this->companyExists('colors', $companyId)],
            'size_id' => ['nullable', $this->companyExists('sizes', $companyId)],
            'attribute_set_id' => ['nullable', $this->companyExists('attribute_sets', $companyId)],
            'price' => 'sometimes|numeric|min:0', 'cost' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|numeric', 'opening_stock' => 'sometimes|numeric',
            'uom' => 'nullable|string|max:100', 'description' => 'nullable|string', 'status' => 'sometimes',
        ]);
        $data['company_id'] = $companyId;
        if (isset($data['status'])) $data['status'] = $this->boolStatus($data['status']);
        if (!$item->exists && !isset($data['stock']) && isset($data['opening_stock'])) $data['stock'] = $data['opening_stock'];
        $item->fill($data)->save();
    }

    private function payload(Item $item): array
    {
        $item->load(['category', 'brand', 'manufacturer', 'color', 'size', 'attributeSet']);
        return [
            'id' => $item->id, 'sku' => $item->sku, 'name' => $item->name,
            'categoryId' => $item->category_id, 'category' => optional($item->category)->name,
            'brandId' => $item->brand_id, 'brand' => optional($item->brand)->name,
            'manufacturerId' => $item->manufacturer_id, 'manufacturer' => optional($item->manufacturer)->name,
            'colorId' => $item->color_id, 'color' => optional($item->color)->name,
            'sizeId' => $item->size_id, 'size' => optional($item->size)->name,
            'attributeSetId' => $item->attribute_set_id, 'attributeSet' => optional($item->attributeSet)->name,
            'price' => (float) $item->price, 'cost' => (float) $item->cost,
            'stock' => (float) $item->stock, 'openingStock' => (float) $item->opening_stock,
            'uom' => $item->uom, 'description' => $item->description,
            'status' => $item->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): Item
    {
        return Item::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function companyExists(string $table, int $companyId)
    {
        return Rule::exists($table, 'id')->where(fn ($q) => $q->where('company_id', $companyId));
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
