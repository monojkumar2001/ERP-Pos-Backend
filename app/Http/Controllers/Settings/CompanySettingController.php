<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\CompanySetting;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $settings = CompanySetting::forCompany($this->requireCompany($request))
            ->pluck('value', 'key');

        return ApiResponse::success($settings);
    }

    public function update(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $validated = $request->validate(['settings' => 'sometimes|array']);
        $settings = $validated['settings'] ?? $request->except(['settings']);

        foreach ($settings as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            CompanySetting::updateOrCreate(
                ['company_id' => $companyId, 'key' => $key],
                ['value' => is_scalar($value) || $value === null ? (string) $value : json_encode($value)]
            );
        }

        $map = CompanySetting::forCompany($companyId)->pluck('value', 'key');

        return ApiResponse::success($map, 'Settings updated');
    }
}
