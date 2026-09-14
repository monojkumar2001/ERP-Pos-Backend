<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AclUserController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $rows = User::where('company_id', $companyId)
            ->orderBy('email')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'email' => $u->email,
                'status' => $u->status ? 'Active' : 'Inactive',
            ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $user = User::where('company_id', $companyId)->findOrFail($id);

        $roles = $user->aclRoles()
            ->wherePivot('company_id', $companyId)
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'roleName' => $r->name,
                'redirectUrl' => $r->redirect_url ?: '/dashboard',
                'status' => $r->pivot->status ?: 'Active',
                'effectiveFrom' => $r->pivot->effective_from
                    ? date('d M Y', strtotime($r->pivot->effective_from))
                    : null,
            ]);

        return ApiResponse::success([
            'id' => $user->id,
            'username' => Str::before($user->email, '@'),
            'fullName' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?: 'N/A',
            'status' => $user->status ? 'Active' : 'Inactive',
            'statusSince' => optional($user->updated_at)->format('d M Y'),
            'userSince' => optional($user->created_at)->format('d M Y'),
            'roles' => $roles,
        ]);
    }

    public function invite(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $role = Role::forCompany($companyId)->findOrFail($data['role_id']);

        $user = DB::transaction(function () use ($companyId, $data, $role) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'phone' => $data['phone'] ?? null,
                    'password' => bcrypt(Str::random(16)),
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'status' => true,
                    'email_verified_at' => now(),
                ]
            );

            if ($user->wasRecentlyCreated === false) {
                if ($user->company_id && (int) $user->company_id !== $companyId) {
                    abort(response()->json(['message' => 'User belongs to another company.'], 422));
                }
                $user->fill([
                    'name' => $data['full_name'],
                    'phone' => $data['phone'] ?? $user->phone,
                    'company_id' => $companyId,
                    'role_id' => $role->id,
                    'status' => true,
                ])->save();
            }

            $user->aclRoles()->syncWithoutDetaching([
                $role->id => [
                    'company_id' => $companyId,
                    'status' => 'Active',
                    'effective_from' => now()->toDateString(),
                ],
            ]);

            return $user;
        });

        return ApiResponse::success([
            'id' => $user->id,
            'email' => $user->email,
            'status' => $user->status ? 'Active' : 'Inactive',
        ], 'User invited', 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $user = User::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:Active,Inactive',
        ]);

        $user->status = $data['status'] === 'Active';
        $user->save();

        return ApiResponse::success([
            'id' => $user->id,
            'email' => $user->email,
            'status' => $user->status ? 'Active' : 'Inactive',
        ], 'User status updated');
    }

    public function assignRole(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $user = User::where('company_id', $companyId)->findOrFail($id);

        $data = $request->validate([
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $user->aclRoles()->syncWithoutDetaching([
            $data['role_id'] => [
                'company_id' => $companyId,
                'status' => 'Active',
                'effective_from' => now()->toDateString(),
            ],
        ]);

        $user->role_id = $data['role_id'];
        $user->save();

        return ApiResponse::success(null, 'Role assigned');
    }
}
