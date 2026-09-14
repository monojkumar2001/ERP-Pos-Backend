<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        if ($user->is_owner) {
            return $next($request);
        }

        $companyId = (int) $user->company_id;
        if (!$companyId) {
            return ApiResponse::error('No company linked to this user.', 422);
        }

        $roleIds = DB::table('role_user')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('status', 'Active')
            ->pluck('role_id')
            ->all();

        if ($user->role_id) {
            $roleIds[] = (int) $user->role_id;
        }

        $roleIds = array_values(array_unique(array_filter($roleIds)));

        if (!$roleIds) {
            return ApiResponse::error('Forbidden.', 403);
        }

        $has = DB::table('permission_role')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'permission_role.role_id')
            ->whereIn('permission_role.role_id', $roleIds)
            ->where('roles.company_id', $companyId)
            ->where('permissions.key', $permission)
            ->exists();

        if (!$has) {
            return ApiResponse::error('Forbidden.', 403);
        }

        return $next($request);
    }
}
