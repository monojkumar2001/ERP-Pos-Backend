<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\PosOutlet;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyProvisioner
{
    /**
     * Create company + owner user + default ERP POS workspace.
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $companyName = trim($data['company']);
            $slug = $this->uniqueSlug($companyName);
            $code = $this->uniqueCode($companyName);

            $company = Company::create([
                'name' => $companyName,
                'slug' => $slug,
                'code' => $code,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'status' => true,
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => bcrypt($data['password']),
                'company_id' => $company->id,
                'is_owner' => true,
                'role_id' => 1,
                'email_verified_at' => null,
                'verification_code' => $data['verification_code'] ?? null,
                'verification_code_expires_at' => $data['verification_code_expires_at'] ?? null,
                'country' => $data['country'] ?? null,
            ]);

            $company->update(['owner_id' => $user->id]);

            $this->seedSettings($company);
            $warehouse = $this->seedWarehouse($company);
            $this->seedOutlet($company, $warehouse);
            $this->seedChartOfAccounts($company);

            // Demo accounting chart + sample transactions
            $demo = new \Database\Seeders\AccountingDemoSeeder();
            $demo->seedExpandedChart($company);
            $demo->seedTransactions($company);

            return compact('company', 'user');
        });
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;
        while (Company::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    protected function uniqueCode(string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'CO', 0, 4));
        $code = $base . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        while (Company::where('code', $code)->exists()) {
            $code = $base . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    protected function seedSettings(Company $company): void
    {
        $defaults = [
            'app_name' => 'Biznify ERP POS',
            'fiscal_year_start' => now()->startOfYear()->toDateString(),
            'invoice_prefix' => 'INV',
            'bill_prefix' => 'BL',
            'pos_enabled' => '1',
            'accounting_enabled' => '1',
        ];

        foreach ($defaults as $key => $value) {
            CompanySetting::create([
                'company_id' => $company->id,
                'key' => $key,
                'value' => $value,
            ]);
        }
    }

    protected function seedWarehouse(Company $company): Warehouse
    {
        return Warehouse::create([
            'company_id' => $company->id,
            'name' => 'Main Warehouse',
            'code' => 'WH-001',
            'is_default' => true,
            'status' => true,
        ]);
    }

    protected function seedOutlet(Company $company, Warehouse $warehouse): void
    {
        PosOutlet::create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Main Outlet',
            'code' => 'OUT-001',
            'is_default' => true,
            'status' => true,
        ]);
    }

    protected function seedChartOfAccounts(Company $company): void
    {
        $accounts = [
            ['type' => 'asset', 'group_name' => 'Cash', 'code' => '1001', 'name' => 'Cash on Hand', 'description' => 'Petty and on-hand cash'],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => '1101', 'name' => 'Main Bank Account', 'description' => 'Primary operating bank'],
            ['type' => 'asset', 'group_name' => 'Receivable', 'code' => '1201', 'name' => 'Accounts Receivable', 'description' => 'Customer receivables'],
            ['type' => 'asset', 'group_name' => 'Inventory', 'code' => '1301', 'name' => 'Inventory Asset', 'description' => 'Stock on hand'],
            ['type' => 'liability', 'group_name' => 'Payable', 'code' => '2001', 'name' => 'Accounts Payable', 'description' => 'Supplier payables'],
            ['type' => 'equity', 'group_name' => "Owner's Equity", 'code' => '3001', 'name' => 'Owner Capital', 'description' => 'Opening capital'],
            ['type' => 'income', 'group_name' => 'Sales', 'code' => '4001', 'name' => 'Sales Income', 'description' => 'Product/service sales'],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => '5001', 'name' => 'Operating Expenses', 'description' => 'Day-to-day expenses'],
            ['type' => 'expense', 'group_name' => 'Cost of Goods Sold', 'code' => '5101', 'name' => 'Cost of Goods Sold', 'description' => 'COGS'],
        ];

        foreach ($accounts as $row) {
            Account::create(array_merge($row, [
                'company_id' => $company->id,
                'status' => true,
            ]));
        }
    }
}
