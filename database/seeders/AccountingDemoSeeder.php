<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Journal;
use App\Models\MoneyTransfer;
use App\Models\RecurringJournal;
use App\Models\User;
use Illuminate\Database\Seeder;

class AccountingDemoSeeder extends Seeder
{
    public function run()
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $company = Company::create([
                'name' => 'Demo Trading Ltd',
                'slug' => 'demo-trading-ltd',
                'code' => 'DEMO0001',
                'email' => 'admin@admin.com',
                'phone' => '01792892198',
                'currency' => 'BDT',
                'timezone' => 'Asia/Dhaka',
                'status' => true,
                'trial_ends_at' => now()->addYear(),
            ]);

            User::query()->update(['company_id' => $company->id]);
            $owner = User::where('email', 'admin@admin.com')->first()
                ?: User::where('email', 'monoj@admin.com')->first()
                ?: User::first();
            if ($owner) {
                $company->update(['owner_id' => $owner->id]);
                $owner->update(['is_owner' => true, 'email_verified_at' => now()]);
            }

            $this->seedExpandedChart($company);
            $this->seedTransactions($company);
            return;
        }

        foreach ($companies as $company) {
            $this->seedExpandedChart($company);
            if (Journal::where('company_id', $company->id)->exists()) {
                continue;
            }
            $this->seedTransactions($company);
        }
    }

    public function seedExpandedChart(Company $company): void
    {
        $accounts = [
            ['type' => 'asset', 'group_name' => 'Cash', 'code' => '1001', 'name' => 'Cash on Hand', 'description' => 'Petty and on-hand cash', 'balance' => 125450.00],
            ['type' => 'asset', 'group_name' => 'Cash', 'code' => '1002', 'name' => 'Petty Cash', 'description' => null, 'balance' => 5200.00],
            ['type' => 'asset', 'group_name' => 'Cash', 'code' => null, 'name' => 'Magura Cash', 'description' => null, 'balance' => 99445.00],
            ['type' => 'asset', 'group_name' => 'Cash', 'code' => null, 'name' => 'Dinajpur Cash', 'description' => null, 'balance' => 2800.00],
            ['type' => 'asset', 'group_name' => 'Cash', 'code' => null, 'name' => 'Bandar Cash', 'description' => null, 'balance' => 518589.00],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => '1101', 'name' => 'Main Bank Account', 'description' => 'Primary operating bank', 'balance' => 892350.50],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => null, 'name' => 'Bank Asia', 'description' => null, 'balance' => 245000.00],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => null, 'name' => 'City Bank', 'description' => null, 'balance' => 320000.00],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => null, 'name' => 'DBBL Bank', 'description' => null, 'balance' => 1245800.00],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => null, 'name' => 'Brack Bank', 'description' => 'ljsabd', 'balance' => 180000.00],
            ['type' => 'asset', 'group_name' => 'Bank', 'code' => null, 'name' => 'IBBL', 'description' => null, 'balance' => 95000.00],
            ['type' => 'asset', 'group_name' => 'Mobile Bank', 'code' => null, 'name' => 'bKash Merchant', 'description' => null, 'balance' => 48920.00],
            ['type' => 'asset', 'group_name' => 'Mobile Bank', 'code' => null, 'name' => 'Nagad Business', 'description' => null, 'balance' => 22150.00],
            ['type' => 'asset', 'group_name' => 'Mobile Bank', 'code' => null, 'name' => 'Rocket', 'description' => null, 'balance' => 8400.00],
            ['type' => 'asset', 'group_name' => 'Receivable', 'code' => '1201', 'name' => 'Accounts Receivable', 'description' => 'Customer receivables', 'balance' => 40693265.87],
            ['type' => 'asset', 'group_name' => 'Inventory', 'code' => '1301', 'name' => 'Inventory Asset', 'description' => 'Stock on hand', 'balance' => 850000.00],
            ['type' => 'liability', 'group_name' => 'Credit Card', 'code' => '2001', 'name' => 'Business Credit Card', 'description' => null, 'balance' => 12000.00],
            ['type' => 'liability', 'group_name' => 'Loan and Line of Credit', 'code' => '2101', 'name' => 'Bank Loan', 'description' => null, 'balance' => 500000.00],
            ['type' => 'liability', 'group_name' => 'Loan and Line of Credit', 'code' => '2102', 'name' => 'Line of Credit', 'description' => null, 'balance' => 0],
            ['type' => 'liability', 'group_name' => 'Payable', 'code' => '2201', 'name' => 'Accounts Payable', 'description' => 'Supplier payables', 'balance' => 22339195.39],
            ['type' => 'liability', 'group_name' => 'Expected Payments to Vendors', 'code' => null, 'name' => 'Goods Received Not Invoiced', 'description' => null, 'balance' => 45000.00],
            ['type' => 'equity', 'group_name' => 'Business Owner Contribution and Drawing', 'code' => '3001', 'name' => "Owner's Contribution", 'description' => null, 'balance' => 1000000.00],
            ['type' => 'equity', 'group_name' => 'Business Owner Contribution and Drawing', 'code' => '3002', 'name' => "Owner's Drawing", 'description' => null, 'balance' => 0],
            ['type' => 'equity', 'group_name' => 'Retained Earnings', 'code' => '3101', 'name' => 'Retained Earnings', 'description' => null, 'balance' => 250000.00],
            ['type' => 'income', 'group_name' => 'Income', 'code' => '4001', 'name' => 'Sales', 'description' => 'Income from selling products or services.', 'balance' => 0],
            ['type' => 'income', 'group_name' => 'Income', 'code' => '4002', 'name' => 'Service Revenue', 'description' => null, 'balance' => 0],
            ['type' => 'income', 'group_name' => 'Other Income', 'code' => '4101', 'name' => 'Interest Income', 'description' => null, 'balance' => 0],
            ['type' => 'income', 'group_name' => 'Other Income', 'code' => null, 'name' => 'Joint Machine rent', 'description' => null, 'balance' => 0],
            ['type' => 'income', 'group_name' => 'Uncategorized Income', 'code' => null, 'name' => 'Cash on Hands', 'description' => null, 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Cost of Goods Sold', 'code' => '5022', 'name' => 'Cost of Goods Sold', 'description' => null, 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Cost of Goods Sold', 'code' => null, 'name' => 'Application Fee', 'description' => null, 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Discounts', 'code' => '5601', 'name' => 'Discount Received', 'description' => 'This account is responsible for received discounts from suppliers.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => '5001', 'name' => 'Accounting Fees', 'description' => 'Accounting or bookkeeping services for your business.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => '5002', 'name' => 'Advertising & Promotion', 'description' => 'Advertising or other costs to promote your business.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => '5003', 'name' => 'Bank Service Charges', 'description' => 'Fees you pay to your bank like transaction charges.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => '5004', 'name' => 'Computer – Hardware', 'description' => 'Desktop or laptop computers, mobile phones, tablets.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => '5005', 'name' => 'Computer – Hosting', 'description' => 'Fees for web storage and access.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Operating Expense', 'code' => null, 'name' => 'বিদ্যুৎ বিল', 'description' => null, 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Payroll Expense', 'code' => '5303', 'name' => 'Payroll – Salary & Wages', 'description' => 'Wages and salaries paid to your employees.', 'balance' => 0],
            ['type' => 'expense', 'group_name' => 'Payroll Expense', 'code' => null, 'name' => 'Advance Salary', 'description' => null, 'balance' => 0],
        ];

        foreach ($accounts as $row) {
            $existing = Account::where('company_id', $company->id)
                ->where('name', $row['name'])
                ->where('type', $row['type'])
                ->first();

            if ($existing) {
                $existing->update([
                    'group_name' => $row['group_name'],
                    'code' => $row['code'] ?? $existing->code,
                    'description' => $row['description'] ?? $existing->description,
                    'balance' => $row['balance'],
                    'status' => true,
                ]);
                continue;
            }

            Account::create(array_merge($row, [
                'company_id' => $company->id,
                'status' => true,
            ]));
        }
    }

    public function seedTransactions(Company $company): void
    {
        $cid = $company->id;

        $journals = [
            ['JV-2026-00000031', '2026-09-01', '14:53:00', 'Test', 59, 'Approved'],
            ['JV-2026-00000032', '2026-09-01', '14:53:00', 'testing', 130, 'Approved'],
            ['JV-2026-00000030', '2026-08-12', '15:03:00', 'dokan rent', 10000, 'Approved'],
            ['JV-2026-00000029', '2026-08-06', '15:06:00', 'agdyqfdyqfdyq', 70000, 'Approved'],
            [null, '2026-07-28', '18:37:00', 'Test', 50, 'Draft'],
            ['JV-2026-00000028', '2026-07-28', '18:37:00', 'Test', 50, 'Approved'],
            [null, '2026-07-15', '16:22:00', 'test', 159, 'Draft'],
            ['JV-2026-00000026', '2026-07-13', '12:51:00', 'corporate sale', 8000, 'Approved'],
            ['JV-2026-00000025', '2026-07-06', '13:23:00', 'Salary Disbursment Account to Account', 2000, 'Approved'],
            ['JV-2026-00000024', '2026-07-05', '17:01:00', 'regular', 5000, 'Approved'],
            ['JV-2026-00000022', '2026-06-21', '17:01:00', 'Cash paid to M\\S Corporation for battery purpose', 50000, 'Approved'],
            ['JV-2026-00000021', '2026-06-21', '14:49:00', 'paid by city bank for store furniture', 60000, 'Approved'],
            ['JV-2026-00000020', '2026-06-21', '14:12:00', 'paid cash for sewing machine', 2000000, 'Approved'],
            ['JV-2026-00000019', '2026-05-17', '12:34:00', 'Cash bank transfer', 20000, 'Approved'],
            ['JV-2026-00000018', '2026-05-09', '07:20:00', 'gdfgdfhds', 500000, 'Approved'],
            ['JV-2026-00000017', '2026-05-03', '16:00:00', 'investment', 50000, 'Approved'],
            ['JV-2026-00000015', '2026-04-19', '16:53:00', 'Land purchase by cash', 1500000, 'Approved'],
            ['JV-2026-00000010', '2026-01-25', '11:42:00', 'Labor cost paid', 15000, 'Approved'],
        ];

        foreach ($journals as $j) {
            Journal::create([
                'company_id' => $cid,
                'reference_number' => $j[0],
                'entry_date' => $j[1],
                'entry_time' => $j[2],
                'description' => $j[3],
                'amount' => $j[4],
                'status' => $j[5],
            ]);
        }

        $expenses = [
            ['DV-2026-00000044', '2026-09-06', '10:15:00', 'Cost of Goods Sold', 'Cash on Hand', 1550, 'Approved'],
            ['DV-2026-00000043', '2026-09-06', '11:20:00', 'Application Fee', 'Bank Asia', 15000, 'Approved'],
            ['DV-2026-00000042', '2026-09-06', '12:05:00', 'বিদ্যুৎ বিল', 'Cash on Hand', 400, 'Approved'],
            ['DV-2026-00000041', '2026-09-05', '14:30:00', 'Cost of Goods Sold', 'City Bank', 110.04, 'Approved'],
            ['DV-2026-00000040', '2026-09-03', '09:45:00', 'Cost of Goods Sold', 'DBBL Bank', 1100, 'Approved'],
            ['DV-2026-00000039', '2026-09-02', '16:10:00', 'Cost of Goods Sold', 'Cash on Hand', 100, 'Approved'],
            ['DV-2026-00000038', '2026-09-02', '16:22:00', 'Cost of Goods Sold', 'Bank Asia', 503.95, 'Approved'],
            ['DV-2026-00000037', '2026-09-02', '17:01:00', 'Cost of Goods Sold', 'Petty Cash', 110, 'Approved'],
            ['DV-2026-00000036', '2026-09-02', '17:15:00', 'Cost of Goods Sold', 'City Bank', 820, 'Approved'],
            ['DV-2026-00000035', '2026-09-02', '18:00:00', 'Cost of Goods Sold', 'Cash on Hand', 1120.05, 'Approved'],
            ['DV-2026-00000034', '2026-08-28', '11:00:00', 'Office expense', 'Bank Asia', 2500, 'Approved'],
            ['DV-2026-00000033', '2026-08-25', '13:20:00', 'Transport', 'Cash on Hand', 850, 'Approved'],
            ['DV-2026-00000032', '2026-08-20', '15:40:00', 'Utilities', 'DBBL Bank', 3200, 'Approved'],
            [null, '2026-08-18', '10:00:00', 'testing', 'Bank Asia', 50, 'Draft'],
            ['DV-2026-00000031', '2026-08-15', '12:30:00', 'Payroll – Salary & Wages', 'Main Bank Account', 45000, 'Approved'],
            ['DV-2026-00000030', '2026-08-10', '09:10:00', 'Bank Service Charges', 'City Bank', 250, 'Approved'],
            ['DV-2026-00000029', '2026-08-05', '14:00:00', 'Advertising & Promotion', 'Bank Asia', 5000, 'Approved'],
            ['DV-2026-00000028', '2026-07-28', '16:45:00', 'Accounting Fees', 'DBBL Bank', 8000, 'Approved'],
        ];

        foreach ($expenses as $e) {
            Expense::create([
                'company_id' => $cid,
                'reference_number' => $e[0],
                'entry_date' => $e[1],
                'entry_time' => $e[2],
                'description' => $e[3],
                'expense_from' => $e[4],
                'amount' => $e[5],
                'status' => $e[6],
            ]);
        }

        $incomes = [
            ['CV-2026-00000014', '2026-09-06', '09:30:00', 'Sales', 'Cash on Hand', 2300, 'Approved'],
            ['CV-2026-00000013', '2026-09-03', '11:15:00', 'Sales', 'City Bank', 1150, 'Approved'],
            ['CV-2026-00000012', '2026-09-02', '10:00:00', 'Sales', 'Bank Asia', 172.5, 'Approved'],
            ['CV-2026-00000011', '2026-09-02', '11:20:00', 'Sales', 'Cash on Hand', 800, 'Approved'],
            ['CV-2026-00000010', '2026-09-02', '12:40:00', 'Sales', 'DBBL Bank', 200, 'Approved'],
            ['CV-2026-00000009', '2026-09-02', '14:10:00', 'Sales', 'Brack Bank', 2040, 'Approved'],
            ['CV-2026-00000008', '2026-09-02', '15:00:00', 'Sales', 'IBBL', 1454.82, 'Approved'],
            ['CV-2026-00000007', '2026-09-02', '16:30:00', 'Discount Given', 'Cash on Hand', 0, 'Approved'],
            ['CV-2026-00000006', '2026-09-02', '17:00:00', 'Sales', 'Petty Cash', 172.5, 'Approved'],
            ['CV-2026-00000005', '2026-09-01', '18:20:00', 'Sales', 'City Bank', 100, 'Approved'],
            ['CV-2026-00000004', '2026-08-28', '10:00:00', 'Service Revenue', 'Main Bank Account', 15000, 'Approved'],
            ['CV-2026-00000003', '2026-08-20', '13:45:00', 'Interest Income', 'DBBL Bank', 2500, 'Approved'],
            ['CV-2026-00000002', '2026-08-15', '09:30:00', 'Joint Machine rent', 'Bank Asia', 8000, 'Approved'],
            ['CV-2026-00000001', '2026-08-10', '11:00:00', 'Sales', 'Cash on Hand', 12500, 'Approved'],
            [null, '2026-08-05', '14:00:00', 'testing', 'Brack Bank', 50, 'Draft'],
        ];

        foreach ($incomes as $i) {
            Income::create([
                'company_id' => $cid,
                'reference_number' => $i[0],
                'entry_date' => $i[1],
                'entry_time' => $i[2],
                'description' => $i[3],
                'income_to' => $i[4],
                'amount' => $i[5],
                'status' => $i[6],
            ]);
        }

        $transfers = [
            ['TM-2026-00000009', '2026-09-01', '14:55:00', 't', 'Bank Asia', 'Cash on Hand', 1000, 'Approved'],
            ['TM-2026-00000008', '2026-08-12', '15:17:00', 'hand', 'Cash on Hand', 'City Bank', 200, 'Approved'],
            ['TM-2026-00000007', '2026-08-01', '10:00:00', 'petty refill', 'Main Bank Account', 'Petty Cash', 5000, 'Approved'],
            ['TM-2026-00000006', '2026-07-20', '16:30:00', 'branch fund', 'DBBL Bank', 'Magura Cash', 25000, 'Approved'],
            ['TM-2026-00000005', '2026-07-10', '12:00:00', 'mobile topup', 'Cash on Hand', 'bKash Merchant', 10000, 'Approved'],
            ['TM-2026-00000004', '2026-06-15', '09:45:00', 'settlement', 'City Bank', 'DBBL Bank', 50000, 'Approved'],
            [null, '2026-06-01', '11:00:00', 'draft transfer', 'Bank Asia', 'IBBL', 500, 'Draft'],
            ['TM-2026-00000003', '2026-05-20', '14:20:00', 'opening', 'Main Bank Account', 'Bandar Cash', 100000, 'Approved'],
        ];

        foreach ($transfers as $t) {
            MoneyTransfer::create([
                'company_id' => $cid,
                'reference_number' => $t[0],
                'entry_date' => $t[1],
                'entry_time' => $t[2],
                'description' => $t[3],
                'transfer_from' => $t[4],
                'transfer_to' => $t[5],
                'amount' => $t[6],
                'status' => $t[7],
            ]);
        }

        $recurring = [
            ['2026-09-01', 'Liabilities', 'বিদ্যুৎ বিল', 'Goods Received Not Invoiced', 5400, '5 Days', '2026-09-06', 'Approve', 'Active'],
            ['2026-08-01', 'Expense', 'Office Rent', 'Cash on Hand', 25000, '1 Months', '2026-09-01', 'Approve', 'Active'],
            ['2026-07-15', 'Expense', 'Payroll – Salary & Wages', 'Main Bank Account', 45000, '1 Months', '2026-08-15', 'Approve', 'Active'],
            ['2026-06-01', 'Expense', 'Internet Bill', 'Bank Asia', 2500, '1 Months', '2026-09-01', 'Approve', 'Active'],
            ['2026-05-01', 'Asset', 'Petty Cash', 'Cash on Hand', 3000, '15 Days', '2026-08-20', 'Approve', 'Active'],
            ['2026-04-01', 'Income', 'Service Revenue', 'City Bank', 10000, '1 Months', '2026-08-01', 'Approve', 'Active'],
            ['2026-03-01', 'Expense', 'Accounting Fees', 'DBBL Bank', 8000, '3 Months', '2026-06-01', 'Approve', 'Active'],
            ['2026-02-01', 'Liabilities', 'Bank Loan', 'Main Bank Account', 20000, '1 Months', '2026-09-01', 'Approve', 'Active'],
            ['2026-01-15', 'Expense', 'Utilities', 'Cash on Hand', 1500, '7 Days', null, 'Approve', 'Active'],
        ];

        foreach ($recurring as $r) {
            RecurringJournal::create([
                'company_id' => $cid,
                'start_date' => $r[0],
                'account_type' => $r[1],
                'debit_account' => $r[2],
                'credit_account' => $r[3],
                'amount' => $r[4],
                'recurring_interval' => $r[5],
                'last_transaction_date' => $r[6],
                'approval_status' => $r[7],
                'operational_status' => $r[8],
            ]);
        }
    }
}
