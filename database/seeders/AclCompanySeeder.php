<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioner;
use Illuminate\Database\Seeder;

class AclCompanySeeder extends Seeder
{
    public function run()
    {
        $this->call(AclPermissionSeeder::class);

        $provisioner = new CompanyProvisioner();

        Company::query()->each(function (Company $company) use ($provisioner) {
            $admin = $provisioner->seedAclRoles($company);

            User::where('company_id', $company->id)->each(function (User $user) use ($company, $admin) {
                if (!$user->role_id || !\App\Models\Role::where('id', $user->role_id)->where('company_id', $company->id)->exists()) {
                    $user->role_id = $admin->id;
                    $user->save();
                }

                $user->aclRoles()->syncWithoutDetaching([
                    $admin->id => [
                        'company_id' => $company->id,
                        'status' => $user->status ? 'Active' : 'Inactive',
                        'effective_from' => optional($user->created_at)->toDateString() ?: now()->toDateString(),
                    ],
                ]);
            });
        });
    }
}
