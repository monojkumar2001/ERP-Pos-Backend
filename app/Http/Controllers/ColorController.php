<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Color;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = Color::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'color_code' => $m->color_code,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new Color, 'Color created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Color updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Color deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }

    private function save(Request $request, Color $model, string $message, int $code = 200)
    {
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'color_code' => ($model->exists ? 'sometimes' : 'required') . '|string|max:50',
            'status' => 'sometimes',
        ]);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $data['company_id'] = $this->requireCompany($request);
        $model->fill($data)->save();

        return ApiResponse::success($this->payload($model), $message, $code);
    }

    private function payload(Color $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'color_code' => $m->color_code,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): Color
    {
        return Color::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
