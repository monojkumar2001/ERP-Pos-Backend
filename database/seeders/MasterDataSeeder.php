<?php

namespace Database\Seeders;

use App\Models\AttributeItem;
use App\Models\AttributeSet;
use App\Models\BinLocation;
use App\Models\Brand;
use App\Models\Color;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Manufacturer;
use App\Models\PosOutlet;
use App\Models\PriceList;
use App\Models\Size;
use App\Models\SizeType;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Models\UomConversion;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run()
    {
        Company::query()->each(fn (Company $company) => $this->seedCompany($company));
    }

    private function seedCompany(Company $company): void
    {
        $cid = $company->id;

        $categories = collect(['Electronics', 'Food', 'Grocery'])->mapWithKeys(function ($name, $position) use ($cid) {
            $model = ItemCategory::updateOrCreate(
                ['company_id' => $cid, 'name' => $name],
                ['parent_id' => null, 'position' => $position, 'status' => true]
            );
            return [$name => $model];
        });

        $manufacturers = collect([
            ['name' => 'ACME', 'description' => 'International manufacturer'],
            ['name' => 'Local Factory', 'description' => 'Local manufacturer'],
        ])->mapWithKeys(function ($row) use ($cid) {
            $model = Manufacturer::updateOrCreate(
                ['company_id' => $cid, 'name' => $row['name']],
                ['description' => $row['description'], 'discount' => null, 'status' => true]
            );
            return [$row['name'] => $model];
        });

        $brands = collect(['Generic', 'Premium'])->mapWithKeys(function ($name) use ($cid) {
            $model = Brand::updateOrCreate(['company_id' => $cid, 'name' => $name], ['discount' => null, 'status' => true]);
            return [$name => $model];
        });

        foreach ([['Red', '#FF0000'], ['Blue', '#0000FF']] as [$name, $code]) {
            Color::updateOrCreate(['company_id' => $cid, 'name' => $name], ['color_code' => $code, 'status' => true]);
        }

        $sizeType = SizeType::updateOrCreate(['company_id' => $cid, 'name' => 'Apparel'], ['status' => true]);
        foreach (['S', 'M', 'L'] as $position => $name) {
            Size::updateOrCreate(
                ['company_id' => $cid, 'size_type_id' => $sizeType->id, 'name' => $name],
                ['position' => $position, 'status' => true]
            );
        }

        $attributeSet = AttributeSet::updateOrCreate(['company_id' => $cid, 'name' => 'Material'], ['status' => true]);
        foreach (['Cotton', 'Polyester'] as $position => $name) {
            AttributeItem::updateOrCreate(
                ['company_id' => $cid, 'attribute_set_id' => $attributeSet->id, 'name' => $name],
                ['position' => $position, 'status' => true]
            );
        }

        $priceLists = collect([
            ['Default', 'Sale', 'Default'],
            ['Sale', 'Sale', 'Retail'],
            ['Purchase', 'Purchase', 'Cost'],
        ])->mapWithKeys(function ($row) use ($cid) {
            $model = PriceList::updateOrCreate(
                ['company_id' => $cid, 'name' => $row[0]],
                ['transaction_type' => $row[1], 'pricebook_type' => $row[2], 'status' => true]
            );
            return [$row[0] => $model];
        });

        UomConversion::updateOrCreate(
            ['company_id' => $cid, 'from_uom' => 'Dozen', 'to_uom' => 'Piece'],
            ['factor' => 12, 'status' => true]
        );

        $items = [
            ['SKU-1001', 'Smartphone', 'Electronics', 'Premium', 'ACME', 25000, 18],
            ['SKU-1002', 'Wireless Headphones', 'Electronics', 'Generic', 'ACME', 3500, 30],
            ['SKU-1003', 'LED Bulb', 'Electronics', 'Generic', 'Local Factory', 250, 80],
            ['SKU-2001', 'Basmati Rice 5kg', 'Food', 'Premium', 'Local Factory', 850, 24],
            ['SKU-3001', 'Cooking Oil 2L', 'Grocery', 'Generic', 'Local Factory', 420, 40],
            ['SKU-3002', 'Laundry Detergent', 'Grocery', 'Generic', 'Local Factory', 180, 55],
        ];
        foreach ($items as $row) {
            Item::updateOrCreate(
                ['company_id' => $cid, 'sku' => $row[0]],
                [
                    'name' => $row[1], 'category_id' => $categories[$row[2]]->id,
                    'brand_id' => $brands[$row[3]]->id, 'manufacturer_id' => $manufacturers[$row[4]]->id,
                    'price' => $row[5], 'cost' => $row[5] * .75, 'stock' => $row[6],
                    'opening_stock' => $row[6], 'uom' => 'Piece', 'status' => true,
                ]
            );
        }

        $customerTypes = collect(['Retail', 'Wholesale'])->mapWithKeys(function ($name) use ($cid) {
            $model = CustomerType::updateOrCreate(['company_id' => $cid, 'name' => $name], ['status' => true]);
            return [$name => $model];
        });
        foreach ([
            ['Walk-in Customer', '01700000001', 'walkin@example.com', 'CUST-001', 'Retail'],
            ['City Mart', '01700000002', 'citymart@example.com', 'CUST-002', 'Wholesale'],
            ['Rahim Traders', '01700000003', 'rahim@example.com', 'CUST-003', 'Wholesale'],
        ] as $row) {
            Customer::updateOrCreate(
                ['company_id' => $cid, 'code' => $row[3]],
                [
                    'name' => $row[0], 'phone' => $row[1], 'email' => $row[2],
                    'customer_type_id' => $customerTypes[$row[4]]->id,
                    'price_list_id' => $priceLists['Default']->id, 'status' => true,
                ]
            );
        }

        $supplierTypes = collect(['Local', 'Import'])->mapWithKeys(function ($name) use ($cid) {
            $model = SupplierType::updateOrCreate(['company_id' => $cid, 'name' => $name], ['status' => true]);
            return [$name => $model];
        });
        foreach ([
            ['Dhaka Supply Co.', '01800000001', 'supply@example.com', 'SUP-001', 'Local'],
            ['Global Imports Ltd.', '01800000002', 'imports@example.com', 'SUP-002', 'Import'],
        ] as $row) {
            Supplier::updateOrCreate(
                ['company_id' => $cid, 'code' => $row[3]],
                [
                    'name' => $row[0], 'phone' => $row[1], 'email' => $row[2],
                    'supplier_type_id' => $supplierTypes[$row[4]]->id, 'status' => true,
                ]
            );
        }

        $warehouse = Warehouse::where('company_id', $cid)->where('name', 'Main Warehouse')->first();
        if ($warehouse) {
            foreach ([['Receiving', 'REC'], ['General Stock', 'GEN']] as [$name, $code]) {
                BinLocation::updateOrCreate(
                    ['company_id' => $cid, 'warehouse_id' => $warehouse->id, 'name' => $name],
                    ['code' => $code, 'status' => true]
                );
            }
        }

        $outlet = PosOutlet::where('company_id', $cid)->where('name', 'Main Outlet')->first();
        if ($outlet) {
            $outlet->update([
                'phone' => '01700000000',
                'address' => 'Main business address',
                'cash_account' => 'Cash on Hand',
                'mobile_banking' => [['provider' => 'bKash', 'number' => '01700000000']],
            ]);
        }
    }
}
