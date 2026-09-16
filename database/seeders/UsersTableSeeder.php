<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roleId = fn (string $slug) => (int) DB::table('roles')->where('slug', $slug)->whereNull('company_id')->value('id');

        $users = [
            [
                'role_id' => $roleId('super-admin') ?: 1,
                'name' => 'Super Admin',
                'country' => 'Bangladesh',
                'phone' => '01792892198',
                'email' => 'monoj@admin.com',
                'password' => bcrypt('monojkumar'),
                'status' => true,
            ],
            [
                'role_id' => $roleId('user') ?: 2,
                'name' => 'user',
                'country' => 'Bangladesh',
                'phone' => '01792892198',
                'email' => 'monoj@user.com',
                'password' => bcrypt('monojkumar'),
                'status' => true,
            ],
            [
                'role_id' => $roleId('admin') ?: 3,
                'name' => 'Admin',
                'country' => 'Bangladesh',
                'phone' => '01792892198',
                'email' => 'admin@admin.com',
                'password' => bcrypt('monojkumar'),
                'status' => true,
            ],
            [
                'role_id' => $roleId('block') ?: 4,
                'name' => 'Block User',
                'country' => 'Bangladesh',
                'phone' => '01792892198',
                'email' => 'monoj@block.com',
                'password' => bcrypt('monojkumar'),
                'status' => true,
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                $user
            );
        }
    }
}
