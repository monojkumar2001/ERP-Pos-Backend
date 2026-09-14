<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Supplier::forCompany($this->requireCompany($request))->with('type')->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'phone_number' => $m->phone,
                'email' => $m->email, 'supplier_type' => optional($m->type)->name,
                'code' => $m->code, 'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { $m = new Supplier; $this->persist($request, $m); return ApiResponse::success($this->payload($m), 'Supplier created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { $m = $this->find($request, $id); $this->persist($request, $m); return ApiResponse::success($this->payload($m), 'Supplier updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Supplier deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }

    private function persist(Request $request, Supplier $model): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'phone' => 'nullable|string|max:100', 'phone_number' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255', 'code' => 'nullable|string|max:100',
            'supplier_type_id' => ['nullable', Rule::exists('supplier_types', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'supplier_type' => 'nullable|string|max:255', 'opening_balance' => 'sometimes|numeric',
            'contact_name' => 'nullable|string|max:255', 'address' => 'nullable|string',
            'city' => 'nullable|string|max:255', 'state' => 'nullable|string|max:255',
            'zip' => 'nullable|string|max:50', 'country' => 'nullable|string|max:255', 'status' => 'sometimes',
        ]);
        if (!empty($data['supplier_type']) && empty($data['supplier_type_id'])) {
            $data['supplier_type_id'] = SupplierType::firstOrCreate(['company_id' => $companyId, 'name' => $data['supplier_type']], ['status' => true])->id;
        }
        unset($data['supplier_type']);
        if (array_key_exists('phone_number', $data)) $data['phone'] = $data['phone_number'];
        unset($data['phone_number']);
        if (isset($data['status'])) $data['status'] = $this->boolStatus($data['status']);
        $data['company_id'] = $companyId;
        $model->fill($data)->save();
    }

    private function payload(Supplier $m): array
    {
        $m->load('type');
        return [
            'id' => $m->id, 'name' => $m->name, 'phone' => $m->phone, 'email' => $m->email,
            'code' => $m->code, 'supplierTypeId' => $m->supplier_type_id,
            'supplierType' => optional($m->type)->name, 'openingBalance' => (float) $m->opening_balance,
            'contactName' => $m->contact_name, 'address' => $m->address, 'city' => $m->city,
            'state' => $m->state, 'zip' => $m->zip, 'country' => $m->country,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): Supplier { return Supplier::forCompany($this->requireCompany($request))->findOrFail($id); }
    private function boolStatus($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active'; }
}
