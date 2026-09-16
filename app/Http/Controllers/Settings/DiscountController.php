<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Discount;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Discount::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'type' => $m->type,
                'value' => $m->value,
                'rate' => $m->type === 'percentage' ? $m->value.'%' : $m->value,
                'applied_to' => $m->applied_to,
                'effective_from' => $m->effective_from?->toDateString(),
                'effective_to' => $m->effective_to?->toDateString() ?: 'N/A',
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);

        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new Discount, 'Discount created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Discount updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Discount deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    public function effectiveDate(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $data = $request->validate([
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);
        $m->update($data);

        return ApiResponse::success($m, 'Effective dates updated');
    }

    private function save(Request $request, Discount $model, string $message, int $code = 200)
    {
        // Frontend form uses `rate`; treat trailing % as percentage type.
        if ($request->filled('rate') && ! $request->filled('value')) {
            $rate = trim((string) $request->input('rate'));
            $isPct = str_ends_with($rate, '%') || ! $request->filled('type');
            $numeric = (float) str_replace('%', '', $rate);
            $request->merge([
                'value' => $numeric,
                'type' => $request->input('type') ?: ($isPct ? 'percentage' : 'fixed'),
            ]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'applied_to' => 'nullable|string|max:255',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'status' => 'sometimes',
        ]);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $model->fill($data)->save();

        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): Discount
    {
        return Discount::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
