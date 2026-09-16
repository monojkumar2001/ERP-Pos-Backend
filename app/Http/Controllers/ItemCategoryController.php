<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\ItemCategory;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemCategoryController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $categories = ItemCategory::forCompany($companyId)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        if ($request->boolean('flat')) {
            $rows = $categories->map(fn ($m) => $this->flatRow($m));
            return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
        }

        $byParent = $categories->groupBy(fn ($m) => $m->parent_id ?? 0);
        $tree = $this->buildTree($byParent, 0);

        return response()->json(['rows' => $tree, 'totalEntries' => $categories->count()]);
    }

    public function store(Request $request) { return $this->save($request, new ItemCategory, 'Category created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Category updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Category deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }

    private function save(Request $request, ItemCategory $model, string $message, int $code = 200)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'parent_id' => [
                'nullable',
                Rule::exists('item_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'parent' => 'nullable|string|max:255',
            'position' => 'sometimes|integer|min:0',
            'status' => 'sometimes',
        ]);

        if (!empty($data['parent']) && empty($data['parent_id'])) {
            if (strtolower($data['parent']) === 'root') {
                $data['parent_id'] = null;
            } else {
                $data['parent_id'] = ItemCategory::forCompany($companyId)
                    ->where('name', $data['parent'])
                    ->value('id');
            }
        }
        unset($data['parent']);

        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $data['company_id'] = $companyId;
        $model->fill($data)->save();

        return ApiResponse::success($this->payload($model), $message, $code);
    }

    private function buildTree($byParent, $parentId): array
    {
        return ($byParent->get($parentId) ?? collect())->map(function ($m) use ($byParent) {
            return array_merge($this->flatRow($m), [
                'children' => $this->buildTree($byParent, $m->id),
            ]);
        })->values()->all();
    }

    private function flatRow(ItemCategory $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'parent_id' => $m->parent_id,
            'position' => $m->position,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function payload(ItemCategory $m): array
    {
        $m->load('parent');
        return [
            'id' => $m->id,
            'name' => $m->name,
            'parent_id' => $m->parent_id,
            'parent' => optional($m->parent)->name ?: 'Root',
            'position' => $m->position,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): ItemCategory
    {
        return ItemCategory::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
