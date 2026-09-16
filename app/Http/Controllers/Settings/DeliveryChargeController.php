<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\DeliveryCharge;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DeliveryChargeController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = DeliveryCharge::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'zone' => $m->zone,
                'charge' => $m->charge,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);

        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new DeliveryCharge, 'Delivery charge created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->find($request, $id)); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Delivery charge updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Delivery charge deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($m, 'Status updated'); }

    public function generate(Request $request)
    {
        $companyId = $this->requireCompany($request);

        if (DeliveryCharge::forCompany($companyId)->exists()) {
            return ApiResponse::error('Delivery charges already exist for this company.', 422);
        }

        $samples = [
            ['name' => 'Dhaka Metro', 'zone' => 'Dhaka', 'charge' => 50],
            ['name' => 'Gazipur', 'zone' => 'Gazipur', 'charge' => 60],
            ['name' => 'Chattogram', 'zone' => 'Chattogram', 'charge' => 80],
        ];

        foreach ($samples as $row) {
            DeliveryCharge::create(array_merge($row, [
                'company_id' => $companyId,
                'status' => true,
            ]));
        }

        return $this->index($request);
    }

    private function save(Request $request, DeliveryCharge $model, string $message, int $code = 200)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'channel_id' => 'nullable|integer|exists:delivery_channels,id',
            'zone' => 'nullable|string|max:255',
            'charge' => 'sometimes|numeric|min:0',
            'status' => 'sometimes',
        ]);
        $data['company_id'] = $this->requireCompany($request);
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $model->fill($data)->save();

        return ApiResponse::success($model, $message, $code);
    }

    private function find(Request $request, $id): DeliveryCharge
    {
        return DeliveryCharge::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
