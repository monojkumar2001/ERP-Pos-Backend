<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Brand;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Brand::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'brand' => $m->name,
                'discount' => $m->discount ?: 'N/A',
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new Brand, 'Brand created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Brand updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Brand deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }
    public function discount(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $d = $request->validate(['discount' => 'nullable|string|max:255']);
        $discount = $d['discount'] ?? null;
        if ($discount === 'N/A' || $discount === '') {
            $discount = null;
        }
        $m->update(['discount' => $discount]);
        return ApiResponse::success($this->payload($m), 'Discount updated');
    }

    private function save(Request $request, Brand $model, string $message, int $code = 200)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'discount' => 'nullable|string|max:255',
            'status' => 'sometimes',
        ]);

        if (empty($data['name']) && !empty($data['brand'])) {
            $data['name'] = $data['brand'];
        }
        unset($data['brand']);

        if (!$model->exists && empty($data['name'])) {
            return ApiResponse::validation(['name' => ['The name field is required.']]);
        }

        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        if (array_key_exists('discount', $data) && ($data['discount'] === 'N/A' || $data['discount'] === '')) {
            $data['discount'] = null;
        }
        $data['company_id'] = $this->requireCompany($request);
        $model->fill($data)->save();

        return ApiResponse::success($this->payload($model), $message, $code);
    }

    private function payload(Brand $m): array
    {
        return [
            'id' => $m->id,
            'brand' => $m->name,
            'name' => $m->name,
            'discount' => $m->discount ?: 'N/A',
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): Brand
    {
        return Brand::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
