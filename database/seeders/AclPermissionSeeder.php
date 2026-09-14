<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\AclPermissions;
use Illuminate\Database\Seeder;

class AclPermissionSeeder extends Seeder
{
    public function run()
    {
        foreach (AclPermissions::GROUPS as $groupKey => $group) {
            foreach ($group['rows'] as $resource) {
                foreach (AclPermissions::ACTIONS as $action) {
                    $key = AclPermissions::key($groupKey, $resource, $action);
                    Permission::updateOrCreate(
                        ['key' => $key],
                        [
                            'group_key' => $groupKey,
                            'group_title' => $group['title'],
                            'resource' => $resource,
                            'action' => $action,
                        ]
                    );
                }
            }
        }
    }
}
