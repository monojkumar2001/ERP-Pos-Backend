<?php

namespace Database\Seeders;

use App\Models\AdjustmentReason;
use App\Models\Company;
use App\Models\Discount;
use App\Models\EmailTemplate;
use App\Models\PaymentMethod;
use App\Models\Tax;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        Company::query()->each(fn (Company $company) => $this->seedCompany($company));
    }

    private function seedCompany(Company $company): void
    {
        $cid = $company->id;

        foreach ([
            ['name' => 'VAT 5%', 'rate' => 5, 'account_code' => 'VAT Payable'],
            ['name' => 'VAT 15%', 'rate' => 15, 'account_code' => 'VAT Payable'],
        ] as $row) {
            Tax::updateOrCreate(
                ['company_id' => $cid, 'name' => $row['name']],
                ['rate' => $row['rate'], 'account_code' => $row['account_code'], 'is_compound' => false, 'status' => true]
            );
        }

        Discount::updateOrCreate(
            ['company_id' => $cid, 'name' => 'New Year Discount'],
            [
                'type' => 'percentage',
                'value' => 15,
                'applied_to' => 'Products',
                'effective_from' => now()->startOfYear()->toDateString(),
                'effective_to' => now()->endOfYear()->toDateString(),
                'status' => true,
            ]
        );

        Discount::updateOrCreate(
            ['company_id' => $cid, 'name' => 'VIP Flat Discount'],
            [
                'type' => 'fixed',
                'value' => 500,
                'applied_to' => 'Products',
                'effective_from' => now()->toDateString(),
                'effective_to' => null,
                'status' => true,
            ]
        );

        foreach ([
            ['name' => 'Cash', 'account_code' => '1001', 'description' => 'Cash on Hand'],
            ['name' => 'Card', 'account_code' => '1101', 'description' => 'Card payments'],
            ['name' => 'Mobile Banking', 'account_code' => '1101', 'description' => 'bKash / Nagad'],
        ] as $row) {
            PaymentMethod::updateOrCreate(
                ['company_id' => $cid, 'name' => $row['name']],
                ['account_code' => $row['account_code'], 'description' => $row['description'], 'status' => true]
            );
        }

        foreach ([
            ['name' => 'Damage', 'type' => 'decrease'],
            ['name' => 'Stock Increase', 'type' => 'increase'],
        ] as $row) {
            AdjustmentReason::updateOrCreate(
                ['company_id' => $cid, 'name' => $row['name']],
                ['type' => $row['type'], 'status' => true]
            );
        }

        EmailTemplate::updateOrCreate(
            ['company_id' => $cid, 'name' => 'Invoice Email'],
            [
                'type' => 'invoice',
                'subject' => 'Your Invoice from {{company_name}}',
                'body' => 'Dear {{customer_name}},\n\nPlease find your invoice attached.\n\nThank you.',
                'status' => true,
            ]
        );
    }
}
