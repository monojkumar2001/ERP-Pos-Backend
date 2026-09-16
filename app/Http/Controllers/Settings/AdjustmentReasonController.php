<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\AdjustmentReason;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class AdjustmentReasonController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = AdjustmentReason::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'adjustment_reason' => $m->name,
                'type' => match ($m->type) {
                    'increase' => 'Add',
                    'decrease' => 'Subtract',
                    default => ucfirst((string) $m->type),
                },
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);

        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new AdjustmentReason, 'Adjustment reason created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Adjustment reason updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Adjustment reason deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, AdjustmentReason $model, string $message, int $code = 200)
    {
        $type = $request->input('type');
        if (is_string($type)) {
            $mapped = match (strtolower($type)) {
                'add', 'increase' => 'increase',
                'subtract', 'decrease' => 'decrease',
                'both' => 'both',
                default => $type,
            };
            $request->merge(['type' => $mapped]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|in:increase,decrease,both',
            'description' => 'nullable|string',
            'account' => 'nullable|string|max:255',
            'status' => 'sometimes',
        ]);
        unset($data['description'], $data['account']);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $model->fill($data)->save();

        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): AdjustmentReason
    {
        return AdjustmentReason::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
