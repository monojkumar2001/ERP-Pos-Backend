<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\AttributeItem;
use App\Models\AttributeSet;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttributeSetController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = AttributeSet::forCompany($this->requireCompany($request))
            ->with('attributes')
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => $this->listRow($m));
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $model = DB::transaction(function () use ($request, $companyId) {
            $model = new AttributeSet(['company_id' => $companyId]);
            $this->persist($request, $model, $companyId);
            return $model;
        });
        return ApiResponse::success($this->payload($model), 'Attribute set created', 201);
    }

    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }

    public function update(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $model = DB::transaction(function () use ($request, $id, $companyId) {
            $model = $this->find($request, $id);
            $this->persist($request, $model, $companyId);
            return $model;
        });
        return ApiResponse::success($this->payload($model), 'Attribute set updated');
    }

    public function destroy(Request $request, $id)
    {
        $this->find($request, $id)->delete();
        return ApiResponse::success(null, 'Attribute set deleted');
    }

    public function status(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $d = $request->validate(['status' => 'required']);
        $m->update(['status' => $this->boolStatus($d['status'])]);
        return ApiResponse::success($this->payload($m), 'Status updated');
    }

    private function persist(Request $request, AttributeSet $model, int $companyId): void
    {
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required_without:attribute_set') . '|nullable|string|max:255',
            'attribute_set' => 'nullable|string|max:255',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required_with:attributes|string|max:255',
            'attributes.*.position' => 'nullable|integer|min:0',
            'added_attributes' => 'nullable|array',
            'added_attributes.*.name' => 'required_with:added_attributes|string|max:255',
            'added_attributes.*.position' => 'nullable|integer|min:0',
            'status' => 'sometimes',
        ]);

        if (empty($data['name']) && !empty($data['attribute_set'])) {
            $data['name'] = $data['attribute_set'];
        }
        unset($data['attribute_set']);

        if (!$model->exists && empty($data['name'])) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                ApiResponse::validation(['name' => ['The name field is required.']])
            );
        }

        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }

        $attributes = $data['attributes'] ?? $data['added_attributes'] ?? null;
        unset($data['attributes'], $data['added_attributes']);

        $data['company_id'] = $companyId;
        $model->fill($data)->save();

        if ($attributes !== null) {
            $this->syncAttributes($model, $attributes, $companyId);
        }
    }

    private function syncAttributes(AttributeSet $model, array $attributes, int $companyId): void
    {
        $model->attributes()->delete();
        foreach (array_values($attributes) as $index => $attribute) {
            AttributeItem::create([
                'company_id' => $companyId,
                'attribute_set_id' => $model->id,
                'name' => $attribute['name'],
                'position' => $attribute['position'] ?? $index,
                'status' => true,
            ]);
        }
        $model->load('attributes');
    }

    private function listRow(AttributeSet $m): array
    {
        return [
            'id' => $m->id,
            'attribute_set' => $m->name,
            'name' => $m->name,
            'attributes' => $m->attributes->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'position' => $a->position,
                'status' => $a->status ? 'Active' : 'Inactive',
            ])->values(),
            'added_attributes' => $m->attributes->map(fn ($a) => ['name' => $a->name, 'position' => $a->position])->values(),
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function payload(AttributeSet $m): array
    {
        $m->load('attributes');
        return $this->listRow($m);
    }

    private function find(Request $request, $id): AttributeSet
    {
        return AttributeSet::forCompany($this->requireCompany($request))->with('attributes')->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
