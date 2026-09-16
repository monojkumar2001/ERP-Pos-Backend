<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\CustomField;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CustomFieldController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = CustomField::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'entity' => $m->entity,
                'module' => $m->entity,
                'applied_on' => $m->entity,
                'field_type' => $m->field_type,
                'data_type' => $m->field_type,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);

        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new CustomField, 'Custom field created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Custom field updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Custom field deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    private function save(Request $request, CustomField $model, string $message, int $code = 200)
    {
        if ($request->filled('applied_on') && ! $request->filled('entity')) {
            $request->merge(['entity' => $request->input('applied_on')]);
        }
        if ($request->filled('data_type') && ! $request->filled('field_type')) {
            $request->merge(['field_type' => $request->input('data_type')]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'entity' => 'required|string|max:255',
            'field_type' => 'required|string|max:255',
            'description' => 'nullable|string',
            'options' => 'nullable|array',
            'is_required' => 'sometimes|boolean',
            'status' => 'sometimes',
        ]);
        unset($data['description']);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $model->fill($data)->save();

        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): CustomField
    {
        return CustomField::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
