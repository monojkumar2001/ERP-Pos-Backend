<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use App\Support\ApiResponse;

trait BelongsToCompany
{
    protected function companyId(Request $request): ?int
    {
        $id = $request->user()?->company_id;

        return $id ? (int) $id : null;
    }

    protected function requireCompany(Request $request): int
    {
        $companyId = $this->companyId($request);

        if (!$companyId) {
            throw new HttpResponseException(
                ApiResponse::error('No company linked to this user.', 422)
            );
        }

        return $companyId;
    }
}
