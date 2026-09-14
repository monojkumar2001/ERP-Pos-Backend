<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class EnsureCompany
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if (!$user->company_id) {
            return ApiResponse::error('No company linked to this user.', 422);
        }

        $request->attributes->set('company_id', (int) $user->company_id);

        return $next($request);
    }
}
