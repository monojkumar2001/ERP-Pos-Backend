<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\PriceList;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class PriceListController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $rows = PriceList::forCompany($this->requireCompany($request))->orderBy('name')->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'transaction_type' => $m->transaction_type,
                'pricebook_type' => $m->pricebook_type,
                'status' => $m->status ? 'Active' : 'Inactive',
            ]);
        return response()->json(['rows' => $rows, 'totalEntries' => $rows->count()]);
    }

    public function store(Request $request) { return $this->save($request, new PriceList, 'Price list created', 201); }
    public function show(Request $request, $id) { return ApiResponse::success($this->payload($this->find($request, $id))); }
    public function update(Request $request, $id) { return $this->save($request, $this->find($request, $id), 'Price list updated'); }
    public function destroy(Request $request, $id) { $this->find($request, $id)->delete(); return ApiResponse::success(null, 'Price list deleted'); }
    public function status(Request $request, $id) { $m = $this->find($request, $id); $d = $request->validate(['status' => 'required']); $m->update(['status' => $this->boolStatus($d['status'])]); return ApiResponse::success($this->payload($m), 'Status updated'); }
    public function deactivate(Request $request, $id)
    {
        $m = $this->find($request, $id);
        $m->update(['status' => false]);
        return ApiResponse::success($this->payload($m), 'Price list deactivated');
    }

    private function save(Request $request, PriceList $model, string $message, int $code = 200)
    {
        $data = $request->validate([
            'name' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'transaction_type' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'pricebook_type' => ($model->exists ? 'sometimes' : 'required') . '|string|max:255',
            'price_list_type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes',
        ]);

        if (empty($data['pricebook_type']) && !empty($data['price_list_type'])) {
            $data['pricebook_type'] = $data['price_list_type'];
        }
        unset($data['price_list_type'], $data['description']);

        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $data['company_id'] = $this->requireCompany($request);
        $model->fill($data)->save();

        return ApiResponse::success($this->payload($model), $message, $code);
    }

    private function payload(PriceList $m): array
    {
        return [
            'id' => $m->id,
            'name' => $m->name,
            'transaction_type' => $m->transaction_type,
            'pricebook_type' => $m->pricebook_type,
            'price_list_type' => $m->pricebook_type,
            'status' => $m->status ? 'Active' : 'Inactive',
        ];
    }

    private function find(Request $request, $id): PriceList
    {
        return PriceList::forCompany($this->requireCompany($request))->findOrFail($id);
    }

    private function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }
}
