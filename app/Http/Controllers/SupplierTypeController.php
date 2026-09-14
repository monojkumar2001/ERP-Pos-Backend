<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\SupplierType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class SupplierTypeController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = SupplierType::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'status' => $m->status ? 'Active' : 'Inactive']);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new SupplierType, 'Supplier type created', 201); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Supplier type updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Supplier type deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $data = $request->validate(['status' => 'required']); $m->update(['status' => filter_var($data['status'], FILTER_VALIDATE_BOOLEAN) || $data['status'] === 'Active']); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, SupplierType $model, string $message, int $code = 200)
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'status' => 'sometimes']);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) $data['status'] = filter_var($data['status'], FILTER_VALIDATE_BOOLEAN) || $data['status'] === 'Active';
        $model->fill($data)->save();
        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): SupplierType { return SupplierType::forCompany($this->requireCompany($request))->findOrFail($id); }
}
