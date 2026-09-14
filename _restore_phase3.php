<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CompanyProvisioner;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\AclPermissionSeeder;
use Illuminate\Support\Facades\Hash;
use ReflectionClass;

(new AclPermissionSeeder())->run();

$provisioner = new CompanyProvisioner();
$ref = new ReflectionClass($provisioner);

$email = 'monoj93roy@gmail.com';
$user = User::where('email', $email)->first();

if (!$user) {
    $result = $provisioner->provision([
        'company' => 'Monoj Demo Co',
        'name' => 'monoj kumar',
        'email' => $email,
        'phone' => '1757859893',
        'password' => 'password',
    ]);
    $user = $result['user'];
    $user->forceFill([
        'email_verified_at' => now(),
        'status' => true,
        'password' => Hash::make('password'),
    ])->save();
    echo "Created user {$email} company_id={$user->company_id}\n";
} else {
    echo "User exists id={$user->id} company_id={$user->company_id}\n";
}

foreach (Company::all() as $company) {
    if (!Warehouse::where('company_id', $company->id)->exists()) {
        $seedWh = $ref->getMethod('seedWarehouse');
        $seedWh->setAccessible(true);
        $wh = $seedWh->invoke($provisioner, $company);
        $seedOut = $ref->getMethod('seedOutlet');
        $seedOut->setAccessible(true);
        $seedOut->invoke($provisioner, $company, $wh);
        $seedAcl = $ref->getMethod('seedAclRoles');
        $seedAcl->setAccessible(true);
        $seedAcl->invoke($provisioner, $company);
        echo "Provisioned WH/Outlet/ACL for company {$company->id}\n";
    }
}

(new MasterDataSeeder())->run();

$cid = $user->company_id;
echo "companies=" . Company::count() . "\n";
echo "company={$cid}\n";
echo "items=" . App\Models\Item::where('company_id', $cid)->count() . "\n";
echo "customers=" . App\Models\Customer::where('company_id', $cid)->count() . "\n";
echo "suppliers=" . App\Models\Supplier::where('company_id', $cid)->count() . "\n";
echo "warehouses=" . Warehouse::where('company_id', $cid)->count() . "\n";
echo "outlets=" . App\Models\PosOutlet::where('company_id', $cid)->count() . "\n";
echo "brands=" . App\Models\Brand::where('company_id', $cid)->count() . "\n";
echo "categories=" . App\Models\ItemCategory::where('company_id', $cid)->count() . "\n";
