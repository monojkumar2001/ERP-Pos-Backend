<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MasterDataApiSmokeTest extends TestCase
{
    public function test_master_list_endpoints_return_rows_shape(): void
    {
        $company = Company::query()->first();
        $this->assertNotNull($company, 'Need at least one company');

        $user = User::query()->where('company_id', $company->id)->first();
        if (!$user) {
            $user = User::query()->first();
            $this->assertNotNull($user, 'Need at least one user');
            $user->company_id = $company->id;
            $user->save();
        }

        Sanctum::actingAs($user);

        $endpoints = [
            '/api/master/manufacturers',
            '/api/master/brands',
            '/api/master/colors',
            '/api/master/items',
            '/api/master/price-lists',
            '/api/master/customer-types',
            '/api/master/customers',
            '/api/master/supplier-types',
            '/api/master/suppliers',
            '/api/master/warehouses',
            '/api/master/bin-locations',
            '/api/master/outlets',
            '/api/master/size-types',
            '/api/master/attribute-sets',
            '/api/master/uom-conversions',
            '/api/master/item-categories',
        ];

        foreach ($endpoints as $url) {
            $res = $this->getJson($url);
            $res->assertOk();
            $res->assertJsonStructure(['rows', 'totalEntries']);
        }
    }
}
