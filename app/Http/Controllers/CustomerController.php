<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Customer::forCompany($this->requireCompany($request))->with('type')->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'phone_number' => $m->phone,
                'email' => $m->email, 'since' => optional($m->created_at)->format('d M Y'),
                'code' => $m->code, 'customer_type' => optional($m->type)->name,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { $m = new Customer; $this->persist($request, $m); return ApiResponse::success($this->payload($m), 'Customer created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { $m = $this->find($request, $id); $this->persist($request, $m); return ApiResponse::success($this->payload($m), 'Customer updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Customer deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }

    private function persist(Request $request, Customer $model): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'phone' => 'nullable|string|max:100', 'phone_number' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255', 'code' => 'nullable|string|max:100',
            'customer_type_id' => ['nullable', Rule::exists('customer_types', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'customer_type' => 'nullable|string|max:255', 'website' => 'nullable|string|max:255',
            'as_supplier' => 'sometimes|boolean',
            'price_list_id' => ['nullable', Rule::exists('price_lists', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'opening_balance' => 'sometimes|numeric', 'credit_limit' => 'sometimes|numeric|min:0',
            'address' => 'nullable|string', 'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255', 'zip' => 'nullable|string|max:50',
            'country' => 'nullable|string|max:255', 'status' => 'sometimes',
        ]);
        if (!empty($data['customer_type']) && empty($data['customer_type_id'])) {
            $data['customer_type_id'] = CustomerType::firstOrCreate(['company_id' => $companyId, 'name' => $data['customer_type']], ['status' => true])->id;
        }
        unset($data['customer_type']);
        if (array_key_exists('phone_number', $data)) $data['phone'] = $data['phone_number'];
        unset($data['phone_number']);
        if (isset($data['status'])) $data['status'] = $this->boolStatus($data['status']);
        $data['company_id'] = $companyId;
        $model->fill($data)->save();
    }

    private function payload(Customer $m): array
    {
        $m->load(['type', 'priceList']);
        return [
            'id' => $m->id, 'name' => $m->name, 'phone' => $m->phone, 'email' => $m->email,
            'code' => $m->code, 'customerTypeId' => $m->customer_type_id,
            'customerType' => optional($m->type)->name, 'website' => $m->website,
            'asSupplier' => (bool) $m->as_supplier, 'priceListId' => $m->price_list_id,
            'priceList' => optional($m->priceList)->name, 'openingBalance' => (float) $m->opening_balance,
            'creditLimit' => (float) $m->credit_limit, 'address' => $m->address,
            'city' => $m->city, 'state' => $m->state, 'zip' => $m->zip,
            'country' => $m->country, 'status' => $m->status ? 'Active' : 'Inactive',
            'since' => optional($m->created_at)->format('d M Y'),
        ];
    }

    private function find(Request $request, $id): Customer { return Customer::forCompany($this->requireCompany($request))->findOrFail($id); }
    private function boolStatus($value): bool { return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active'; }
}
