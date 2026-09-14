<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\BinLocation;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BinLocationController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = BinLocation::forCompany($this->requireCompany($request))->with('warehouse')->orderBy('name')->get()
            ->map(fn ($m) => ['id' => $m->id, 'warehouse_id' => $m->warehouse_id, 'warehouse' => optional($m->warehouse)->name, 'name' => $m->name, 'code' => $m->code, 'status' => $m->status ? 'Active' : 'Inactive']);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new BinLocation, 'Bin location created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)->load('warehouse')); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Bin location updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Bin location deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, BinLocation $model, string $message, int $code = 200)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'name' => 'required|string|max:255', 'code' => 'nullable|string|max:100', 'status' => 'sometimes',
        ]);
        $data['company_id'] = $companyId;
        if (isset($data['status'])) $data['status'] = $this->boolStatus($data['status']);
        $model->fill($data)->save();
        return ApiResponse::success($model->load('warehouse'), $message, $code);
    }

    private function find(Request $request, $id): BinLocation { return BinLocation::forCompany($this->requireCompany($request))->findOrFail($id); }
    private function boolStatus($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active'; }
}
