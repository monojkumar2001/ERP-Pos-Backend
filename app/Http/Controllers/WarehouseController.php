<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Warehouse;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Warehouse::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'code' => $m->code, 'is_default' => (bool) $m->is_default, 'status' => $m->status ? 'Active' : 'Inactive']);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new Warehouse, 'Warehouse created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Warehouse updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Warehouse deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, Warehouse $model, string $message, int $code = 200)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'code' => 'nullable|string|max:100', 'is_default' => 'sometimes|boolean', 'status' => 'sometimes']);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) $data['status'] = $this->boolStatus($data['status']);
        $model->fill($data)->save();
        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): Warehouse { return Warehouse::forCompany($this->requireCompany($request))->findOrFail($id); }
    private function boolStatus($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active'; }
}
