<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\UomConversion;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class UomConversionController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = UomConversion::forCompany($this->requireCompany($request))->orderBy('from_uom')->orderBy('to_uom')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'from_uom' => $m->from_uom,
                'to_uom' => $m->to_uom,
                'factor' => (float) $m->factor,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new UomConversion, 'UoM conversion created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'UoM conversion updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'UoM conversion deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }

    private function save(Request $request, UomConversion $model, string $message, int $code = 200)
    {
        $data = $request->validate([
            'from_uom' => ($model->exists ? 'sometimes' : 'required') . '|string|max:100',
            'to_uom' => ($model->exists ? 'sometimes' : 'required') . '|string|max:100',
            'factor' => ($model->exists ? 'sometimes' : 'required') . '|numeric|min:0',
            'status' => 'sometimes',
        ]);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $data['company_id'] = $this->requireCompany($request);
        $model->fill($data)->save();

        return ApiResponse::success($this->payload($model), $message, $code);
    }

    private function payload(UomConversion $m): array
    {
        return [
            'id' => $m->id,
            'from_uom' => $m->from_uom,
            'to_uom' => $m->to_uom,
            'factor' => (float) $m->factor,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): UomConversion
    {
        return UomConversion::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
