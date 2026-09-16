<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Tax;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Tax::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'rate' => $m->rate,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);

        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new Tax, 'Tax created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Tax updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Tax deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, Tax $model, string $message, int $code = 200)
    {
        if ($request->filled('account') && ! $request->filled('account_code')) {
            $request->merge(['account_code' => $request->input('account')]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'rate' => 'required|numeric|min:0',
            'account_code' => 'nullable|string|max:255',
            'is_compound' => 'sometimes|boolean',
            'status' => 'sometimes',
            'collected_on_sales' => 'sometimes|boolean',
            'paid_on_purchase' => 'sometimes|boolean',
        ]);
        unset($data['collected_on_sales'], $data['paid_on_purchase']);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $model->fill($data)->save();

        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): Tax
    {
        return Tax::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
