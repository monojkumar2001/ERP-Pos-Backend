<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Journal;
use App\Models\MoneyTransfer;
use App\Models\RecurringJournal;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    protected function companyId(Request $request): ?int
    {
        return $request->user()?->company_id;
    }

    protected function requireCompany(Request $request): int
    {
        $companyId = $this->companyId($request);
        if (!$companyId) {
            throw new HttpResponseException(
                response()->json(['message' => 'No company linked to this user.'], 422)
            );
        }
        return (int) $companyId;
    }

    protected function money($amount): string
    {
        $n = (float) $amount;
        $formatted = number_format(abs($n), 2, '.', ',');
        // Trim trailing .00 for cleaner UI when whole number
        $formatted = preg_replace('/\.00$/', '', $formatted);
        $sign = $n < 0 ? '-' : '';
        return '৳ ' . $sign . $formatted;
    }

    protected function formatDate($date): string
    {
        if (!$date) {
            return '';
        }
        return Carbon::parse($date)->format('j M Y');
    }

    protected function formatTime($time): string
    {
        if (!$time) {
            return '';
        }
        try {
            return Carbon::parse($time)->format('h:i A');
        } catch (\Throwable $e) {
            return (string) $time;
        }
    }

    public function dashboard(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $from = $request->query('from');
        $to = $request->query('to');
        $monthLabel = now()->format('M, Y');

        $incomeQuery = Income::where('company_id', $companyId)->where('status', 'Approved');
        $expenseQuery = Expense::where('company_id', $companyId)->where('status', 'Approved');

        if ($from) {
            $incomeQuery->whereDate('entry_date', '>=', $from);
            $expenseQuery->whereDate('entry_date', '>=', $from);
        }
        if ($to) {
            $incomeQuery->whereDate('entry_date', '<=', $to);
            $expenseQuery->whereDate('entry_date', '<=', $to);
        }

        $totalIncome = (clone $incomeQuery)->sum('amount');
        $totalExpense = (clone $expenseQuery)->sum('amount');
        $profit = $totalIncome - $totalExpense;

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthIncome = Income::where('company_id', $companyId)
            ->where('status', 'Approved')
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->sum('amount');
        $monthExpense = Expense::where('company_id', $companyId)
            ->where('status', 'Approved')
            ->whereBetween('entry_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $receivable = Account::where('company_id', $companyId)
            ->where('group_name', 'Receivable')
            ->sum('balance');
        $payable = Account::where('company_id', $companyId)
            ->where('group_name', 'Payable')
            ->sum('balance');

        $kpis = [
            [
                'title' => 'Income',
                'amount' => $this->money($totalIncome),
                'month' => $monthLabel,
                'monthAmount' => $this->money($monthIncome),
                'icon' => 'fa-coins',
                'iconBg' => '#d7e8f5',
                'iconColor' => '#448dc0',
            ],
            [
                'title' => 'Expense',
                'amount' => $this->money($totalExpense),
                'month' => $monthLabel,
                'monthAmount' => $this->money($monthExpense),
                'icon' => 'fa-money-check-alt',
                'iconBg' => '#fbd2ca',
                'iconColor' => '#f55151',
            ],
            [
                'title' => 'Profit',
                'amount' => $this->money($profit),
                'month' => $monthLabel,
                'monthAmount' => $this->money($monthIncome - $monthExpense),
                'icon' => 'fa-hand-holding-usd',
                'iconBg' => '#daf6ca',
                'iconColor' => '#63ba33',
            ],
            [
                'title' => 'Customer Payments Expectations',
                'amount' => $this->money($receivable),
                'month' => $monthLabel,
                'monthAmount' => $this->money($receivable),
                'icon' => 'fa-clipboard-list',
                'iconBg' => '#fee2fe',
                'iconColor' => '#b319b3',
            ],
            [
                'title' => 'Supplier Payments Expectations',
                'amount' => $this->money($payable),
                'month' => $monthLabel,
                'monthAmount' => $this->money($payable),
                'icon' => 'fa-wallet',
                'iconBg' => '#e4f3ea',
                'iconColor' => '#486575',
            ],
            [
                'title' => 'Upcoming',
                'amount' => $this->money($receivable - $payable),
                'month' => $monthLabel,
                'monthAmount' => $this->money($receivable - $payable),
                'icon' => 'fa-file-invoice-dollar',
                'iconBg' => '#f9efc1',
                'iconColor' => '#f39e4d',
            ],
        ];

        $colors = ['#448dc0', '#63ba33', '#f39e4d', '#b319b3', '#f55151', '#486575', '#9b59b6', '#1abc9c', '#95a5a6'];

        $incomeByCat = Income::where('company_id', $companyId)
            ->where('status', 'Approved')
            ->select('description', DB::raw('SUM(amount) as total'))
            ->groupBy('description')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
        $incomeTotal = max(1, $incomeByCat->sum('total'));
        $incomeCategories = $incomeByCat->values()->map(function ($row, $i) use ($colors, $incomeTotal) {
            return [
                'label' => $row->description ?: 'Other',
                'value' => (int) round(($row->total / $incomeTotal) * 100),
                'color' => $colors[$i % count($colors)],
            ];
        })->all();

        $expenseByCat = Expense::where('company_id', $companyId)
            ->where('status', 'Approved')
            ->select('description', DB::raw('SUM(amount) as total'))
            ->groupBy('description')
            ->orderByDesc('total')
            ->limit(9)
            ->get();
        $expenseTotal = max(1, $expenseByCat->sum('total'));
        $expenseCategories = $expenseByCat->values()->map(function ($row, $i) use ($colors, $expenseTotal) {
            return [
                'label' => $row->description ?: 'Other',
                'value' => (int) round(($row->total / $expenseTotal) * 100),
                'color' => $colors[$i % count($colors)],
            ];
        })->all();

        $latestIncomes = Income::where('company_id', $companyId)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                $this->formatDate($r->entry_date),
                $r->description ?: 'Income',
                $this->money($r->amount),
            ])
            ->all();

        $latestExpenses = Expense::where('company_id', $companyId)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                $this->formatDate($r->entry_date),
                $r->description ?: 'Expense',
                $this->money($r->amount),
            ])
            ->all();

        $mapAccounts = function ($group) use ($companyId) {
            return Account::where('company_id', $companyId)
                ->where('group_name', $group)
                ->where('status', true)
                ->orderBy('name')
                ->get()
                ->map(fn ($a) => [$a->name, $this->money($a->balance)])
                ->all();
        };

        return response()->json([
            'kpis' => $kpis,
            'incomeCategories' => $incomeCategories,
            'expenseCategories' => $expenseCategories,
            'latestIncomes' => $latestIncomes,
            'latestExpenses' => $latestExpenses,
            'cashAccounts' => $mapAccounts('Cash'),
            'bankAccounts' => $mapAccounts('Bank'),
            'mobileAccounts' => $mapAccounts('Mobile Bank'),
        ]);
    }

    public function accounts(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $accounts = Account::where('company_id', $companyId)
            ->orderBy('type')
            ->orderBy('group_name')
            ->orderBy('code')
            ->orderBy('name')
            ->get();

        $typeMap = [
            'expense' => 'expense',
            'income' => 'income',
            'equity' => 'equity',
            'liability' => 'liabilities',
            'asset' => 'asset',
        ];

        $tabs = [
            'expense' => ['id' => 'expense', 'label' => 'EXPENSE', 'count' => 0],
            'income' => ['id' => 'income', 'label' => 'INCOME', 'count' => 0],
            'equity' => ['id' => 'equity', 'label' => "OWNER'S EQUITY", 'count' => 0],
            'liabilities' => ['id' => 'liabilities', 'label' => 'LIABILITIES', 'count' => 0],
            'asset' => ['id' => 'asset', 'label' => 'ASSET', 'count' => 0],
        ];

        $grouped = [
            'expense' => [],
            'income' => [],
            'equity' => [],
            'liabilities' => [],
            'asset' => [],
        ];

        foreach ($accounts as $acc) {
            $tabKey = $typeMap[$acc->type] ?? $acc->type;
            if (!isset($grouped[$tabKey])) {
                continue;
            }
            $tabs[$tabKey]['count']++;
            $groupName = $acc->group_name ?: 'Other';
            if (!isset($grouped[$tabKey][$groupName])) {
                $grouped[$tabKey][$groupName] = [
                    'name' => $groupName,
                    'accounts' => [],
                ];
            }
            $grouped[$tabKey][$groupName]['accounts'][] = [
                'id' => $acc->id,
                'code' => $acc->code ?: '',
                'name' => $acc->name,
                'desc' => $acc->description,
                'status' => $acc->status ? 'Active' : 'Inactive',
                'balance' => $this->money($acc->balance ?? 0),
            ];
        }

        $data = [];
        foreach ($grouped as $key => $groups) {
            $data[$key] = array_values($groups);
        }

        return response()->json([
            'tabs' => array_values($tabs),
            'data' => $data,
        ]);
    }

    public function journals(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $rows = Journal::where('company_id', $companyId)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'reference_number' => $r->reference_number ?: 'N/A',
                'date' => $this->formatDate($r->entry_date),
                'time' => $this->formatTime($r->entry_time),
                'description' => $r->description,
                'amount' => $this->money($r->amount),
                'status' => $r->status,
            ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
            'totalAmount' => $this->money($rows->count() ? Journal::where('company_id', $companyId)->sum('amount') : 0),
        ]);
    }

    public function expenses(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $query = Expense::where('company_id', $companyId)->orderByDesc('entry_date')->orderByDesc('id');
        $sum = (clone $query)->sum('amount');
        $rows = $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'reference_number' => $r->reference_number ?: 'N/A',
            'date' => $this->formatDate($r->entry_date),
            'time' => $this->formatTime($r->entry_time),
            'description' => $r->description,
            'expense_from' => $r->expense_from,
            'amount' => $this->money($r->amount),
            'status' => $r->status,
        ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
            'totalAmount' => $this->money($sum),
        ]);
    }

    public function incomes(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $query = Income::where('company_id', $companyId)->orderByDesc('entry_date')->orderByDesc('id');
        $sum = (clone $query)->sum('amount');
        $rows = $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'reference_number' => $r->reference_number ?: 'N/A',
            'date' => $this->formatDate($r->entry_date),
            'time' => $this->formatTime($r->entry_time),
            'description' => $r->description,
            'income_to' => $r->income_to,
            'amount' => $this->money($r->amount),
            'status' => $r->status,
        ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
            'totalAmount' => $this->money($sum),
        ]);
    }

    public function transfers(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $query = MoneyTransfer::where('company_id', $companyId)->orderByDesc('entry_date')->orderByDesc('id');
        $sum = (clone $query)->sum('amount');
        $rows = $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'reference_number' => $r->reference_number ?: 'N/A',
            'date' => $this->formatDate($r->entry_date),
            'time' => $this->formatTime($r->entry_time),
            'description' => $r->description,
            'transfer_from' => $r->transfer_from,
            'amount' => $this->money($r->amount),
            'status' => $r->status,
        ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
            'totalAmount' => $this->money($sum),
        ]);
    }

    public function recurringJournals(Request $request)
    {
        $companyId = $this->requireCompany($request);

        $query = RecurringJournal::where('company_id', $companyId)->orderByDesc('start_date')->orderByDesc('id');
        $sum = (clone $query)->sum('amount');
        $rows = $query->get()->map(fn ($r) => [
            'id' => $r->id,
            'date' => $this->formatDate($r->start_date),
            'account_type' => $r->account_type,
            'debit_account' => $r->debit_account,
            'credit_account' => $r->credit_account,
            'amount' => $this->money($r->amount),
            'recurring_interval' => $r->recurring_interval,
            'last_transaction_date' => $this->formatDate($r->last_transaction_date),
            'approval_status' => $r->approval_status,
            'operational_status' => $r->operational_status,
        ]);

        return response()->json([
            'rows' => $rows,
            'totalEntries' => $rows->count(),
            'totalAmount' => $this->money($sum),
        ]);
    }
}
