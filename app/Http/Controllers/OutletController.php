<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\PosOutlet;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutletController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = PosOutlet::forCompany($this->requireCompany($request))->with('warehouse')->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'number' => $m->phone,
                'warehouse_id' => $m->warehouse_id, 'warehouse' => optional($m->warehouse)->name,
                'cash_account' => $m->cash_account, 'account_balance' => '৳ 0.00',
                'last_user' => $m->last_user, 'address' => $m->address,
                'mobile_banking' => $m->mobile_banking ?: [],
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new PosOutlet, 'Outlet created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)->load('warehouse')); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Outlet updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Outlet deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, PosOutlet $model, string $message, int $code = 200)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'name' => 'required|string|max:255', 'code' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:100', 'number' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:255', 'cash_account' => 'nullable|string|max:255',
            'mobile_banking' => 'nullable|array', 'last_user' => 'nullable|string|max:255',
            'is_default' => 'sometimes|boolean', 'status' => 'sometimes',
        ]);
        if (array_key_exists('number', $data)) $data['phone'] = $data['number'];
        unset($data['number']);
        $data['company_id'] = $companyId;
        if (isset($data['status'])) $data['status'] = $this->boolStatus($data['status']);
        $model->fill($data)->save();
        return ApiResponse::success($model->load('warehouse'), $message, $code);
    }

    private function find(Request $request, $id): PosOutlet { return PosOutlet::forCompany($this->requireCompany($request))->findOrFail($id); }
    private function boolStatus($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active'; }
}
