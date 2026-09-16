<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Size;
use App\Models\SizeType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SizeTypeController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = SizeType::forCompany($this->requireCompany($request))
            ->with('sizes')
            ->orderBy('name')
            ->get()
            ->map(fn ($m) => $this->listRow($m));
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $model = DB::transaction(function () use ($request, $companyId) {
            $model = new SizeType(['company_id' => $companyId]);
            $this->persist($request, $model, $companyId);
            return $model;
        });
        return ApiResponse::success($this->payload($model), 'Size type created', 201);
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
        return ApiResponse::success($this->payload($model), 'Size type updated');
    }

    public function destroy(Request $request, $id)
    {
        $this->find($request, $id)->delete();
        return ApiResponse::success(null, 'Size type deleted');
    }

    public function status(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $d = $request->validate(['status' => 'required']);
        $m->update(['status' => $this->boolStatus($d['status'])]);
        return ApiResponse::success($this->payload($m), 'Status updated');
    }

    private function persist(Request $request, SizeType $model, int $companyId): void
    {
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required_without:size_type') . '|nullable|string|max:255',
            'size_type' => 'nullable|string|max:255',
            'sizes' => 'nullable|array',
            'sizes.*.name' => 'required_with:sizes|string|max:255',
            'sizes.*.position' => 'nullable|integer|min:0',
            'added_sizes' => 'nullable|array',
            'added_sizes.*.name' => 'required_with:added_sizes|string|max:255',
            'added_sizes.*.position' => 'nullable|integer|min:0',
            'status' => 'sometimes',
        ]);

        if (empty($data['name']) && !empty($data['size_type'])) {
            $data['name'] = $data['size_type'];
        }
        unset($data['size_type']);

        if (!$model->exists && empty($data['name'])) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                ApiResponse::validation(['name' => ['The name field is required.']])
            );
        }

        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }

        $sizes = $data['sizes'] ?? $data['added_sizes'] ?? null;
        unset($data['sizes'], $data['added_sizes']);

        $data['company_id'] = $companyId;
        $model->fill($data)->save();

        if ($sizes !== null) {
            $this->syncSizes($model, $sizes, $companyId);
        }
    }

    private function syncSizes(SizeType $model, array $sizes, int $companyId): void
    {
        $model->sizes()->delete();
        foreach (array_values($sizes) as $index => $size) {
            Size::create([
                'company_id' => $companyId,
                'size_type_id' => $model->id,
                'name' => $size['name'],
                'position' => $size['position'] ?? $index,
                'status' => true,
            ]);
        }
        $model->load('sizes');
    }

    private function listRow(SizeType $m): array
    {
        return [
            'id' => $m->id,
            'size_type' => $m->name,
            'name' => $m->name,
            'sizes' => $m->sizes->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'position' => $s->position,
                'status' => $s->status ? 'Active' : 'Inactive',
            ])->values(),
            'added_sizes' => $m->sizes->map(fn ($s) => ['name' => $s->name, 'position' => $s->position])->values(),
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function payload(SizeType $m): array
    {
        $m->load('sizes');
        return $this->listRow($m);
    }

    private function find(Request $request, $id): SizeType
    {
        return SizeType::forCompany($this->requireCompany($request))->with('sizes')->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
