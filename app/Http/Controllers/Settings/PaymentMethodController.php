<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\PaymentMethod;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = PaymentMethod::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'payment_type' => $m->account_type ?: 'Other',
                'account_type' => $m->account_type ?: 'Other',
                'account' => $m->account_code,
                'expense_account' => $m->expense_account ?: 'N/A',
                'charge' => $m->charge ?? 0,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);

        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new PaymentMethod, 'Payment method created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Payment method updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Payment method deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, PaymentMethod $model, string $message, int $code = 200)
    {
        if ($request->filled('account') && ! $request->filled('account_code')) {
            $request->merge(['account_code' => $request->input('account')]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'account_code' => 'nullable|string|max:255',
            'account_type' => 'nullable|string|max:255',
            'expense_account' => 'nullable|string|max:255',
            'charge' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'sometimes',
        ]);
        if (empty($data['description']) && ! empty($data['account_type'])) {
            $data['description'] = $data['account_type'];
        }
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $model->fill($data)->save();

        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): PaymentMethod
    {
        return PaymentMethod::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
