<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\AttributeItem;
use App\Models\AttributeSet;
use App\Models\Brand;
use App\Models\Color;
use App\Models\ItemCategory;
use App\Models\Manufacturer;
use App\Models\PriceList;
use App\Models\Size;
use App\Models\SizeType;
use App\Models\UomConversion;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    use BelongsToCompany;

    public function manufacturers(Request $request) { return $this->simpleIndex($request, Manufacturer::class, fn ($m) => ['id' => $m->id, 'manufacturer' => $m->name, 'description' => $m->description, 'discount' => $m->discount ?: 'N/A', 'status' => $this->statusLabel($m)]); }
    public function storeManufacturer(Request $request) { return $this->saveSimple($request, new Manufacturer, 'manufacturer', ['description' => 'nullable|string', 'discount' => 'nullable|string|max:100'], 'Manufacturer created'); }
    public function updateManufacturer(Request $request, $id) { return $this->saveSimple($request, $this->find($request, Manufacturer::class, $id), 'manufacturer', ['description' => 'nullable|string', 'discount' => 'nullable|string|max:100'], 'Manufacturer updated'); }

    public function brands(Request $request) { return $this->simpleIndex($request, Brand::class, fn ($m) => ['id' => $m->id, 'brand' => $m->name, 'discount' => $m->discount ?: 'N/A', 'status' => $this->statusLabel($m)]); }
    public function storeBrand(Request $request) { return $this->saveSimple($request, new Brand, 'brand', ['discount' => 'nullable|string|max:100'], 'Brand created'); }
    public function updateBrand(Request $request, $id) { return $this->saveSimple($request, $this->find($request, Brand::class, $id), 'brand', ['discount' => 'nullable|string|max:100'], 'Brand updated'); }

    public function colors(Request $request) { return $this->simpleIndex($request, Color::class, fn ($m) => ['id' => $m->id, 'name' => $m->name, 'color_code' => $m->color_code, 'status' => $this->statusLabel($m)]); }
    public function storeColor(Request $request) { return $this->saveSimple($request, new Color, 'name', ['color_code' => 'required|string|max:50'], 'Color created'); }
    public function updateColor(Request $request, $id) { return $this->saveSimple($request, $this->find($request, Color::class, $id), 'name', ['color_code' => 'required|string|max:50'], 'Color updated'); }

    public function priceLists(Request $request) { return $this->simpleIndex($request, PriceList::class, fn ($m) => ['id' => $m->id, 'name' => $m->name, 'transaction_type' => $m->transaction_type, 'pricebook_type' => $m->pricebook_type, 'status' => $this->statusLabel($m)]); }
    public function storePriceList(Request $request) { return $this->saveSimple($request, new PriceList, 'name', ['transaction_type' => 'required|string|max:100', 'pricebook_type' => 'required|string|max:100'], 'Price list created'); }
    public function updatePriceList(Request $request, $id) { return $this->saveSimple($request, $this->find($request, PriceList::class, $id), 'name', ['transaction_type' => 'required|string|max:100', 'pricebook_type' => 'required|string|max:100'], 'Price list updated'); }
    public function showPriceList(Request $request, $id) { return ApiResponse::success($this->find($request, PriceList::class, $id)); }

    public function uomConversions(Request $request) { return $this->simpleIndex($request, UomConversion::class, fn ($m) => ['id' => $m->id, 'from_uom' => $m->from_uom, 'to_uom' => $m->to_uom, 'factor' => (float) $m->factor, 'status' => $this->statusLabel($m)]); }
    public function storeUomConversion(Request $request) { return $this->saveSimple($request, new UomConversion, 'from_uom', ['to_uom' => 'required|string|max:100', 'factor' => 'required|numeric|min:0'], 'UoM conversion created'); }
    public function updateUomConversion(Request $request, $id) { return $this->saveSimple($request, $this->find($request, UomConversion::class, $id), 'from_uom', ['to_uom' => 'required|string|max:100', 'factor' => 'required|numeric|min:0'], 'UoM conversion updated'); }

    public function sizeTypes(Request $request)
    {
        return $this->simpleIndex($request, SizeType::class, fn ($m) => [
            'id' => $m->id, 'size_type' => $m->name,
            'added_sizes' => $m->sizes->pluck('name')->values(),
            'status' => $this->statusLabel($m),
        ], ['sizes']);
    }

    public function showSizeType(Request $request, $id)
    {
        $model = $this->find($request, SizeType::class, $id)->load('sizes');
        return ApiResponse::success(['id' => $model->id, 'size_type' => $model->name, 'added_sizes' => $model->sizes->map->only(['id', 'name']), 'status' => $this->statusLabel($model)]);
    }

    public function storeSizeType(Request $request) { return $this->saveNested($request, new SizeType, 'size_type', 'added_sizes', Size::class, 'size_type_id', 'Size type created'); }
    public function updateSizeType(Request $request, $id) { return $this->saveNested($request, $this->find($request, SizeType::class, $id), 'size_type', 'added_sizes', Size::class, 'size_type_id', 'Size type updated'); }

    public function attributeSets(Request $request)
    {
        return $this->simpleIndex($request, AttributeSet::class, fn (AttributeSet $m) => [
            'id' => $m->id, 'attribute_set' => $m->name,
            'added_attributes' => $m->getRelation('attributes')->pluck('name')->values(),
            'status' => $this->statusLabel($m),
        ], ['attributes']);
    }

    public function showAttributeSet(Request $request, $id)
    {
        $model = $this->find($request, AttributeSet::class, $id)->load('attributes');
        return ApiResponse::success(['id' => $model->id, 'attribute_set' => $model->name, 'added_attributes' => $model->getRelation('attributes')->map->only(['id', 'name']), 'status' => $this->statusLabel($model)]);
    }

    public function storeAttributeSet(Request $request) { return $this->saveNested($request, new AttributeSet, 'attribute_set', 'added_attributes', AttributeItem::class, 'attribute_set_id', 'Attribute set created'); }
    public function updateAttributeSet(Request $request, $id) { return $this->saveNested($request, $this->find($request, AttributeSet::class, $id), 'attribute_set', 'added_attributes', AttributeItem::class, 'attribute_set_id', 'Attribute set updated'); }

    public function categories(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $all = ItemCategory::forCompany($companyId)->orderBy('position')->orderBy('name')->get();
        $build = function ($parentId) use (&$build, $all) {
            return $all->where('parent_id', $parentId)->values()->map(fn (ItemCategory $m) => [
                'id' => $m->id, 'name' => $m->name, 'parent_id' => $m->parent_id,
                'position' => $m->position, 'status' => $this->statusLabel($m),
                'children' => $build($m->id),
            ]);
        };
        return response()->json(['rows' => $build(null), 'totalEntries' => $all->count()]);
    }

    public function storeCategory(Request $request) { return $this->saveCategory($request, new ItemCategory, 'Category created'); }
    public function updateCategory(Request $request, $id) { return $this->saveCategory($request, $this->find($request, ItemCategory::class, $id), 'Category updated'); }

    public function status(Request $request, string $resource, $id)
    {
        $map = $this->resourceMap();
        abort_unless(isset($map[$resource]), 404);
        $model = $this->find($request, $map[$resource], $id);
        $data = $request->validate(['status' => 'required']);
        $model->update(['status' => $this->statusValue($data['status'])]);
        return ApiResponse::success($model, 'Status updated');
    }

    public function show(Request $request, string $resource, $id)
    {
        $map = $this->resourceMap();
        abort_unless(isset($map[$resource]), 404);
        return ApiResponse::success($this->find($request, $map[$resource], $id));
    }

    public function destroy(Request $request, string $resource, $id)
    {
        $map = $this->resourceMap();
        abort_unless(isset($map[$resource]), 404);
        $this->find($request, $map[$resource], $id)->delete();
        return ApiResponse::success(null, 'Deleted');
    }

    private function simpleIndex(Request $request, string $class, callable $transform, array $with = [])
    {
        $rows = $class::forCompany($this->requireCompany($request))->with($with)->orderBy('name')->get()->map($transform);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    private function saveSimple(Request $request, Model $model, string $inputName, array $rules, string $message)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate(array_merge([$inputName => 'required|string|max:255', 'status' => 'sometimes'], $rules));
        if ($model instanceof UomConversion) {
            // keep from_uom / to_uom / factor as-is
        } else {
            $data['name'] = $data[$inputName];
            unset($data[$inputName]);
        }
        $data['company_id'] = $companyId;
        if (array_key_exists('status', $data)) $data['status'] = $this->statusValue($data['status']);
        $model->fill($data)->save();
        return ApiResponse::success($model, $message, $model->wasRecentlyCreated ? 201 : 200);
    }

    private function saveNested(Request $request, Model $model, string $nameKey, string $childrenKey, string $childClass, string $foreignKey, string $message)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([$nameKey => 'required|string|max:255', $childrenKey => 'sometimes|array', $childrenKey . '.*' => 'string|max:255', 'status' => 'sometimes']);
        DB::transaction(function () use ($model, $data, $companyId, $nameKey, $childrenKey, $childClass, $foreignKey) {
            $model->fill(['company_id' => $companyId, 'name' => $data[$nameKey], 'status' => isset($data['status']) ? $this->statusValue($data['status']) : ($model->exists ? $model->status : true)])->save();
            if (array_key_exists($childrenKey, $data)) {
                $childClass::where($foreignKey, $model->id)->delete();
                foreach (array_values($data[$childrenKey]) as $position => $name) {
                    $childClass::create(['company_id' => $companyId, $foreignKey => $model->id, 'name' => $name, 'position' => $position, 'status' => true]);
                }
            }
        });
        return ApiResponse::success($model->fresh(), $message, $model->wasRecentlyCreated ? 201 : 200);
    }

    private function saveCategory(Request $request, ItemCategory $model, string $message)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'position' => 'sometimes|integer',
            'status' => 'sometimes',
            'parent_id' => ['nullable', Rule::exists('item_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
        ]);
        if ($model->exists && isset($data['parent_id']) && (int) $data['parent_id'] === (int) $model->id) abort(422, 'A category cannot be its own parent.');
        $data['company_id'] = $companyId;
        if (isset($data['status'])) $data['status'] = $this->statusValue($data['status']);
        $model->fill($data)->save();
        return ApiResponse::success($model, $message, $model->wasRecentlyCreated ? 201 : 200);
    }

    private function find(Request $request, string $class, $id): Model
    {
        return $class::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function statusLabel(Model $model): string { return $model->status ? 'Active' : 'Inactive'; }
    private function statusValue($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active'; }

    private function resourceMap(): array
    {
        return [
            'manufacturers' => Manufacturer::class, 'brands' => Brand::class,
            'colors' => Color::class, 'size-types' => SizeType::class,
            'attribute-sets' => AttributeSet::class, 'categories' => ItemCategory::class,
            'price-lists' => PriceList::class, 'uom-conversions' => UomConversion::class,
        ];
    }
}
