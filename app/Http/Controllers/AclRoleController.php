<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\AclPermissions;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AclRoleController extends Controller
{
    use BelongsToCompany;

    public function index(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $rows = Role::forCompany($companyId)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'role_name' => $role->name,
                'redirect_url' => $role->redirect_url ?: '/dashboard',
                'is_system' => (bool) $role->is_system,
            ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'redirect_url' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        $role = DB::transaction(function () use ($companyId, $data) {
            $role = Role::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($companyId, $data['name']),
                'redirect_url' => $data['redirect_url'],
                'description' => $data['description'] ?? null,
                'is_system' => false,
                'status' => true,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });

        return ApiResponse::success($this->rolePayload($role->fresh('permissions')), 'Role created', 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $role = $this->findCompanyRole($companyId, $id);

        $users = $role->assignedUsers()
            ->wherePivot('company_id', $companyId)
            ->orderBy('email')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'username' => Str::before($u->email, '@'),
                'fullName' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone ?: 'N/A',
                'status' => $u->pivot->status ?: 'Active',
                'effectiveFrom' => $u->pivot->effective_from
                    ? date('d M Y', strtotime($u->pivot->effective_from))
                    : null,
            ]);

        return ApiResponse::success([
            'id' => $role->id,
            'roleName' => $role->name,
            'redirectUrl' => $role->redirect_url ?: '/dashboard',
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'permissions' => AclPermissions::matrixFromKeys($role->permissions->pluck('key')),
            'users' => $users,
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $role = $this->findCompanyRole($companyId, $id);

        if ($role->is_system && strcasecmp($role->name, 'Administrator') === 0) {
            // Allow permission/redirect edits but keep name
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('roles', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($role->id),
            ],
            'redirect_url' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'nullable|array',
        ]);

        if ($role->is_system) {
            $data['name'] = $role->name;
        }

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'slug' => $role->is_system ? $role->slug : $this->uniqueSlug((int) $role->company_id, $data['name'], $role->id),
                'redirect_url' => $data['redirect_url'],
                'description' => $data['description'] ?? null,
            ]);
            $this->syncPermissions($role, $data['permissions'] ?? []);
        });

        return ApiResponse::success($this->rolePayload($role->fresh('permissions')), 'Role updated');
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $role = $this->findCompanyRole($companyId, $id);

        if ($role->is_system) {
            return ApiResponse::error('System role cannot be deleted.', 422);
        }

        $role->delete();

        return ApiResponse::success(null, 'Role deleted');
    }

    public function clone(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $source = $this->findCompanyRole($companyId, $id);

        $role = DB::transaction(function () use ($companyId, $source) {
            $name = $source->name . ' (Copy)';
            $role = Role::create([
                'company_id' => $companyId,
                'name' => $name,
                'slug' => $this->uniqueSlug($companyId, $name),
                'redirect_url' => $source->redirect_url,
                'description' => $source->description,
                'is_system' => false,
                'status' => true,
            ]);

            $role->permissions()->sync($source->permissions()->pluck('permissions.id'));

            return $role;
        });

        return ApiResponse::success($this->rolePayload($role->fresh('permissions')), 'Role cloned', 201);
    }

    public function inviteUser(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $role = $this->findCompanyRole($companyId, $id);

        $data = $request->validate([
            'email' => 'required|email',
            'full_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

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

        if (!$user->company_id) {
            $user->company_id = $companyId;
            $user->save();
        }

        if ((int) $user->company_id !== $companyId) {
            return ApiResponse::error('User belongs to another company.', 422);
        }

        $user->aclRoles()->syncWithoutDetaching([
            $role->id => [
                'company_id' => $companyId,
                'status' => 'Active',
                'effective_from' => now()->toDateString(),
            ],
        ]);

        return ApiResponse::success([
            'user_id' => $user->id,
            'role_id' => $role->id,
        ], 'User invited to role');
    }

    public function permissionCatalog()
    {
        return ApiResponse::success([
            'groups' => collect(AclPermissions::GROUPS)->map(fn ($g, $key) => [
                'key' => $key,
                'title' => $g['title'],
                'rows' => $g['rows'],
            ])->values(),
            'actions' => AclPermissions::ACTIONS,
        ]);
    }

    protected function findCompanyRole(int $companyId, $id): Role
    {
        return Role::forCompany($companyId)->findOrFail($id);
    }

    protected function uniqueSlug(int $companyId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 1;
        while (
            Role::where('company_id', $companyId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    protected function syncPermissions(Role $role, array $matrix): void
    {
        $keys = AclPermissions::keysFromMatrix($matrix);
        if (!$keys) {
            $role->permissions()->sync([]);
            return;
        }

        $ids = Permission::whereIn('key', $keys)->pluck('id');
        $role->permissions()->sync($ids);
    }

    protected function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'role_name' => $role->name,
            'name' => $role->name,
            'redirect_url' => $role->redirect_url,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'permissions' => AclPermissions::matrixFromKeys($role->permissions->pluck('key')),
        ];
    }
}
