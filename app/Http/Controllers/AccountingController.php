<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Journal;
use App\Models\JournalLine;
use App\Models\MoneyTransfer;
use App\Models\RecurringJournal;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    use BelongsToCompany;

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
            'transfer_to' => $r->transfer_to,
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

    // --- Accounts (85) ---

    public function storeAccount(Request $request)
    {
        $account = new Account;
        $this->persistAccount($request, $account);

        return ApiResponse::success($this->accountPayload($account), 'Account created', 201);
    }

    public function showAccount(Request $request, $id)
    {
        return ApiResponse::success($this->accountPayload($this->findAccount($request, $id)));
    }

    public function updateAccount(Request $request, $id)
    {
        $account = $this->findAccount($request, $id);
        $this->persistAccount($request, $account);

        return ApiResponse::success($this->accountPayload($account), 'Account updated');
    }

    public function destroyAccount(Request $request, $id)
    {
        $account = $this->findAccount($request, $id);

        if (Expense::where('account_id', $account->id)->exists()
            || Income::where('account_id', $account->id)->exists()
            || JournalLine::where('account_id', $account->id)->exists()) {
            return ApiResponse::error('Account is referenced by transactions and cannot be deleted.', 422);
        }

        $account->delete();

        return ApiResponse::success(null, 'Account deleted');
    }

    public function reorderAccounts(Request $request)
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => ['required', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'items.*.sort_order' => 'required|integer|min:0',
            'items.*.parent_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'items.*.group_name' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                $update = [
                    'sort_order' => $item['sort_order'],
                    'parent_id' => $item['parent_id'] ?? null,
                ];
                if (array_key_exists('group_name', $item)) {
                    $update['group_name'] = $item['group_name'];
                }
                Account::where('id', $item['id'])->update($update);
            }
        });

        return ApiResponse::success(null, 'Accounts reordered');
    }

    // --- Journals (86) ---

    public function storeJournal(Request $request)
    {
        $journal = new Journal(['status' => 'Draft']);
        $this->persistJournal($request, $journal);

        return ApiResponse::success($this->journalPayload($journal->fresh(['lines.account'])), 'Journal created', 201);
    }

    public function showJournal(Request $request, $id)
    {
        return ApiResponse::success($this->journalPayload($this->findJournal($request, $id)->load('lines.account')));
    }

    public function updateJournal(Request $request, $id)
    {
        $journal = $this->findJournal($request, $id);
        if ($journal->status !== 'Draft') {
            return ApiResponse::error('Only draft journals can be updated.', 422);
        }
        $this->persistJournal($request, $journal);

        return ApiResponse::success($this->journalPayload($journal->fresh(['lines.account'])), 'Journal updated');
    }

    public function destroyJournal(Request $request, $id)
    {
        $journal = $this->findJournal($request, $id);
        if ($journal->status !== 'Draft') {
            return ApiResponse::error('Only draft journals can be deleted.', 422);
        }
        $journal->delete();

        return ApiResponse::success(null, 'Journal deleted');
    }

    public function journalStatus(Request $request, $id)
    {
        $journal = $this->findJournal($request, $id);
        $data = $request->validate(['status' => 'required|in:Draft,Approved']);

        if ($data['status'] === 'Approved') {
            return $this->approveJournal($journal);
        }

        return ApiResponse::error('Approved journals cannot be set back to draft.', 422);
    }

    public function approveJournalEntry(Request $request, $id)
    {
        return $this->approveJournal($this->findJournal($request, $id));
    }

    // --- Expenses (87) ---

    public function storeExpense(Request $request)
    {
        $expense = new Expense(['status' => 'Draft']);
        $this->persistExpense($request, $expense);

        return ApiResponse::success($this->expensePayload($expense), 'Expense created', 201);
    }

    public function showExpense(Request $request, $id)
    {
        return ApiResponse::success($this->expensePayload($this->findExpense($request, $id)));
    }

    public function updateExpense(Request $request, $id)
    {
        $expense = $this->findExpense($request, $id);
        if ($expense->status !== 'Draft') {
            return ApiResponse::error('Only draft expenses can be updated.', 422);
        }
        $this->persistExpense($request, $expense);

        return ApiResponse::success($this->expensePayload($expense), 'Expense updated');
    }

    public function destroyExpense(Request $request, $id)
    {
        $expense = $this->findExpense($request, $id);
        if ($expense->status !== 'Draft') {
            return ApiResponse::error('Only draft expenses can be deleted.', 422);
        }
        $expense->delete();

        return ApiResponse::success(null, 'Expense deleted');
    }

    public function expenseStatus(Request $request, $id)
    {
        return $this->updateEntryStatus(
            $this->findExpense($request, $id),
            $request,
            fn (Expense $e) => $this->applyExpenseBalance($e, 1),
            fn (Expense $e) => $this->applyExpenseBalance($e, -1),
            fn ($e) => $this->expensePayload($e)
        );
    }

    // --- Incomes (88) ---

    public function storeIncome(Request $request)
    {
        $income = new Income(['status' => 'Draft']);
        $this->persistIncome($request, $income);

        return ApiResponse::success($this->incomePayload($income), 'Income created', 201);
    }

    public function showIncome(Request $request, $id)
    {
        return ApiResponse::success($this->incomePayload($this->findIncome($request, $id)));
    }

    public function updateIncome(Request $request, $id)
    {
        $income = $this->findIncome($request, $id);
        if ($income->status !== 'Draft') {
            return ApiResponse::error('Only draft incomes can be updated.', 422);
        }
        $this->persistIncome($request, $income);

        return ApiResponse::success($this->incomePayload($income), 'Income updated');
    }

    public function destroyIncome(Request $request, $id)
    {
        $income = $this->findIncome($request, $id);
        if ($income->status !== 'Draft') {
            return ApiResponse::error('Only draft incomes can be deleted.', 422);
        }
        $income->delete();

        return ApiResponse::success(null, 'Income deleted');
    }

    public function incomeStatus(Request $request, $id)
    {
        return $this->updateEntryStatus(
            $this->findIncome($request, $id),
            $request,
            fn (Income $i) => $this->applyIncomeBalance($i, 1),
            fn (Income $i) => $this->applyIncomeBalance($i, -1),
            fn ($i) => $this->incomePayload($i)
        );
    }

    // --- Transfers (89) ---

    public function storeTransfer(Request $request)
    {
        $transfer = new MoneyTransfer(['status' => 'Draft']);
        $this->persistTransfer($request, $transfer);

        return ApiResponse::success($this->transferPayload($transfer), 'Transfer created', 201);
    }

    public function showTransfer(Request $request, $id)
    {
        return ApiResponse::success($this->transferPayload($this->findTransfer($request, $id)));
    }

    public function updateTransfer(Request $request, $id)
    {
        $transfer = $this->findTransfer($request, $id);
        if ($transfer->status !== 'Draft') {
            return ApiResponse::error('Only draft transfers can be updated.', 422);
        }
        $this->persistTransfer($request, $transfer);

        return ApiResponse::success($this->transferPayload($transfer), 'Transfer updated');
    }

    public function destroyTransfer(Request $request, $id)
    {
        $transfer = $this->findTransfer($request, $id);
        if ($transfer->status !== 'Draft') {
            return ApiResponse::error('Only draft transfers can be deleted.', 422);
        }
        $transfer->delete();

        return ApiResponse::success(null, 'Transfer deleted');
    }

    public function transferStatus(Request $request, $id)
    {
        return $this->updateEntryStatus(
            $this->findTransfer($request, $id),
            $request,
            fn (MoneyTransfer $t) => $this->applyTransferBalance($t, 1),
            fn (MoneyTransfer $t) => $this->applyTransferBalance($t, -1),
            fn ($t) => $this->transferPayload($t)
        );
    }

    // --- Recurring journals (90) ---

    public function storeRecurringJournal(Request $request)
    {
        $recurring = new RecurringJournal;
        $this->persistRecurringJournal($request, $recurring);

        return ApiResponse::success($this->recurringJournalPayload($recurring), 'Recurring journal created', 201);
    }

    public function showRecurringJournal(Request $request, $id)
    {
        return ApiResponse::success($this->recurringJournalPayload($this->findRecurringJournal($request, $id)));
    }

    public function updateRecurringJournal(Request $request, $id)
    {
        $recurring = $this->findRecurringJournal($request, $id);
        $this->persistRecurringJournal($request, $recurring);

        return ApiResponse::success($this->recurringJournalPayload($recurring), 'Recurring journal updated');
    }

    public function destroyRecurringJournal(Request $request, $id)
    {
        $this->findRecurringJournal($request, $id)->delete();

        return ApiResponse::success(null, 'Recurring journal deleted');
    }

    public function runRecurringJournal(Request $request, $id)
    {
        $companyId = $this->requireCompany($request);
        $recurring = $this->findRecurringJournal($request, $id);

        if ($recurring->operational_status !== 'Active') {
            return ApiResponse::error('Recurring journal is not active.', 422);
        }

        $debitAccountId = $recurring->debit_account_id;
        $creditAccountId = $recurring->credit_account_id;

        if (!$debitAccountId && $recurring->debit_account) {
            $debitAccountId = Account::where('company_id', $companyId)->where('name', $recurring->debit_account)->value('id');
        }
        if (!$creditAccountId && $recurring->credit_account) {
            $creditAccountId = Account::where('company_id', $companyId)->where('name', $recurring->credit_account)->value('id');
        }

        if (!$debitAccountId || !$creditAccountId) {
            return ApiResponse::error('Debit and credit accounts are required to run this recurring journal.', 422);
        }

        $approved = in_array($recurring->approval_status, ['Approve', 'Approved'], true);
        $today = now()->toDateString();

        $journal = DB::transaction(function () use ($companyId, $recurring, $debitAccountId, $creditAccountId, $approved, $today) {
            $journal = Journal::create([
                'company_id' => $companyId,
                'reference_number' => null,
                'entry_date' => $today,
                'entry_time' => now()->format('H:i:s'),
                'description' => 'Recurring: ' . ($recurring->debit_account ?: 'Debit') . ' / ' . ($recurring->credit_account ?: 'Credit'),
                'amount' => $recurring->amount,
                'status' => $approved ? 'Approved' : 'Draft',
            ]);

            JournalLine::create([
                'journal_id' => $journal->id,
                'account_id' => $debitAccountId,
                'debit' => $recurring->amount,
                'credit' => 0,
                'description' => $recurring->debit_account,
                'sort_order' => 0,
            ]);

            JournalLine::create([
                'journal_id' => $journal->id,
                'account_id' => $creditAccountId,
                'debit' => 0,
                'credit' => $recurring->amount,
                'description' => $recurring->credit_account,
                'sort_order' => 1,
            ]);

            if ($approved) {
                $journal->load('lines.account');
                foreach ($journal->lines as $line) {
                    $this->applyJournalLineBalance($line->account, (float) $line->debit, (float) $line->credit);
                }
            }

            $recurring->update(['last_transaction_date' => $today]);

            return $journal;
        });

        return ApiResponse::success(
            $this->journalPayload($journal->fresh(['lines.account'])),
            'Recurring journal executed',
            201
        );
    }

    // --- Helpers ---

    protected function normalizeAccountType(string $type): string
    {
        return $type === 'liabilities' ? 'liability' : $type;
    }

    protected function accountTypeRule(bool $required = true): array
    {
        $rules = [Rule::in(['expense', 'income', 'equity', 'liabilities', 'asset', 'liability'])];
        array_unshift($rules, $required ? 'required' : 'sometimes');

        return $rules;
    }

    protected function findAccount(Request $request, $id): Account
    {
        return Account::where('company_id', $this->requireCompany($request))->findOrFail($id);
    }

    protected function findJournal(Request $request, $id): Journal
    {
        return Journal::where('company_id', $this->requireCompany($request))->findOrFail($id);
    }

    protected function findExpense(Request $request, $id): Expense
    {
        return Expense::where('company_id', $this->requireCompany($request))->findOrFail($id);
    }

    protected function findIncome(Request $request, $id): Income
    {
        return Income::where('company_id', $this->requireCompany($request))->findOrFail($id);
    }

    protected function findTransfer(Request $request, $id): MoneyTransfer
    {
        return MoneyTransfer::where('company_id', $this->requireCompany($request))->findOrFail($id);
    }

    protected function findRecurringJournal(Request $request, $id): RecurringJournal
    {
        return RecurringJournal::where('company_id', $this->requireCompany($request))->findOrFail($id);
    }

    protected function boolStatus($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) || $value === 'Active';
    }

    protected function persistAccount(Request $request, Account $account): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'type' => $this->accountTypeRule(!$account->exists),
            'name' => ($account->exists ? 'sometimes' : 'required') . '|string|max:255',
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('accounts', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($account->id),
            ],
            'group_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'status' => 'sometimes',
            'parent_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'sort_order' => 'sometimes|integer|min:0',
            'balance' => 'sometimes|numeric',
        ]);

        if (isset($data['type'])) {
            $data['type'] = $this->normalizeAccountType($data['type']);
        }
        if (isset($data['status'])) {
            $data['status'] = $this->boolStatus($data['status']);
        }
        $data['company_id'] = $companyId;
        $account->fill($data)->save();
    }

    protected function accountPayload(Account $account): array
    {
        return [
            'id' => $account->id,
            'type' => $account->type === 'liability' ? 'liabilities' : $account->type,
            'group_name' => $account->group_name,
            'code' => $account->code,
            'name' => $account->name,
            'description' => $account->description,
            'status' => $account->status ? 'Active' : 'Inactive',
            'balance' => (float) $account->balance,
            'balanceFormatted' => $this->money($account->balance),
            'parent_id' => $account->parent_id,
            'sort_order' => $account->sort_order,
        ];
    }

    protected function validateJournalLines(Request $request, int $companyId, bool $requireHeader = true): array
    {
        $data = $request->validate([
            'reference_number' => 'nullable|string|max:100',
            'entry_date' => ($requireHeader ? 'required' : 'sometimes') . '|date',
            'entry_time' => 'nullable',
            'description' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.account_id' => ['required', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:500',
        ]);

        $totalDebit = collect($data['lines'])->sum(fn ($l) => (float) $l['debit']);
        $totalCredit = collect($data['lines'])->sum(fn ($l) => (float) $l['credit']);
        $data['amount'] = max($totalDebit, $totalCredit);

        return $data;
    }

    protected function persistJournal(Request $request, Journal $journal): void
    {
        $companyId = $this->requireCompany($request);
        $data = $this->validateJournalLines($request, $companyId, !$journal->exists);
        $lines = $data['lines'];
        unset($data['lines']);

        DB::transaction(function () use ($journal, $data, $lines, $companyId) {
            if (!$journal->exists) {
                $data['company_id'] = $companyId;
                if (empty($data['reference_number'])) {
                    $data['reference_number'] = $this->nextJournalReference($companyId);
                }
                if (empty($data['entry_time'])) {
                    $data['entry_time'] = now()->format('H:i:s');
                }
            }
            $journal->fill($data)->save();
            $journal->lines()->delete();

            foreach ($lines as $i => $line) {
                JournalLine::create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        });
    }

    protected function nextJournalReference(int $companyId): string
    {
        return $this->nextDocReference($companyId, Journal::class, 'JV');
    }

    protected function nextDocReference(int $companyId, string $modelClass, string $code): string
    {
        $year = now()->format('Y');
        $prefix = "{$code}-{$year}-";
        $last = $modelClass::where('company_id', $companyId)
            ->where('reference_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('reference_number');
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }

    protected function approveJournal(Journal $journal)
    {
        if ($journal->status === 'Approved') {
            return ApiResponse::success($this->journalPayload($journal->load('lines.account')), 'Journal already approved');
        }
        if ($journal->status !== 'Draft') {
            return ApiResponse::error('Only draft journals can be approved.', 422);
        }

        $journal->load('lines.account');
        if ($journal->lines->isEmpty()) {
            return ApiResponse::error('Journal must have at least one line.', 422);
        }

        $totalDebit = $journal->lines->sum(fn ($l) => (float) $l->debit);
        $totalCredit = $journal->lines->sum(fn ($l) => (float) $l->credit);

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return ApiResponse::error('Debit and credit totals must be equal before approval.', 422);
        }

        DB::transaction(function () use ($journal) {
            foreach ($journal->lines as $line) {
                $this->applyJournalLineBalance($line->account, (float) $line->debit, (float) $line->credit);
            }
            $journal->update(['status' => 'Approved']);
        });

        return ApiResponse::success($this->journalPayload($journal->fresh(['lines.account'])), 'Journal approved');
    }

    protected function applyJournalLineBalance(Account $account, float $debit, float $credit): void
    {
        if (in_array($account->type, ['asset', 'expense'], true)) {
            $account->balance = (float) $account->balance + $debit - $credit;
        } else {
            $account->balance = (float) $account->balance + $credit - $debit;
        }
        $account->save();
    }

    protected function journalPayload(Journal $journal): array
    {
        return [
            'id' => $journal->id,
            'reference_number' => $journal->reference_number,
            'entry_date' => $journal->entry_date?->format('Y-m-d'),
            'entry_date_formatted' => $this->formatDate($journal->entry_date),
            'entry_time' => $journal->entry_time,
            'entry_time_formatted' => $this->formatTime($journal->entry_time),
            'description' => $journal->description,
            'amount' => (float) $journal->amount,
            'amount_formatted' => $this->money($journal->amount),
            'status' => $journal->status,
            'lines' => $journal->lines->map(fn ($line) => [
                'id' => $line->id,
                'account_id' => $line->account_id,
                'account_name' => $line->account?->name,
                'account_code' => $line->account?->code,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'description' => $line->description,
                'sort_order' => $line->sort_order,
            ])->values()->all(),
        ];
    }

    protected function persistExpense(Request $request, Expense $expense): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'reference_number' => 'nullable|string|max:100',
            'entry_date' => 'required|date',
            'entry_time' => 'nullable',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'expense_from' => 'nullable|string|max:255',
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'status' => 'sometimes|in:Draft,Approved',
        ]);

        if (!empty($data['account_id'])) {
            $data['expense_from'] = Account::where('company_id', $companyId)->where('id', $data['account_id'])->value('name')
                ?? ($data['expense_from'] ?? null);
        }

        $data['company_id'] = $companyId;
        if (!$expense->exists) {
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->nextDocReference($companyId, Expense::class, 'EX');
            }
            if (empty($data['entry_time'])) {
                $data['entry_time'] = now()->format('H:i:s');
            }
            if (!isset($data['status'])) {
                $data['status'] = 'Draft';
            }
        }
        $expense->fill($data)->save();
    }

    protected function expensePayload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'reference_number' => $expense->reference_number,
            'entry_date' => $expense->entry_date?->format('Y-m-d'),
            'entry_date_formatted' => $this->formatDate($expense->entry_date),
            'entry_time' => $expense->entry_time,
            'entry_time_formatted' => $this->formatTime($expense->entry_time),
            'description' => $expense->description,
            'expense_from' => $expense->expense_from,
            'account_id' => $expense->account_id,
            'amount' => (float) $expense->amount,
            'amount_formatted' => $this->money($expense->amount),
            'status' => $expense->status,
        ];
    }

    protected function applyExpenseBalance(Expense $expense, int $direction): void
    {
        if (!$expense->account_id) {
            return;
        }
        $account = Account::find($expense->account_id);
        if (!$account) {
            return;
        }
        $account->balance = (float) $account->balance - ((float) $expense->amount * $direction);
        $account->save();
    }

    protected function persistIncome(Request $request, Income $income): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'reference_number' => 'nullable|string|max:100',
            'entry_date' => 'required|date',
            'entry_time' => 'nullable',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'income_to' => 'nullable|string|max:255',
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'status' => 'sometimes|in:Draft,Approved',
        ]);

        if (!empty($data['account_id'])) {
            $data['income_to'] = Account::where('company_id', $companyId)->where('id', $data['account_id'])->value('name')
                ?? ($data['income_to'] ?? null);
        }

        $data['company_id'] = $companyId;
        if (!$income->exists) {
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->nextDocReference($companyId, Income::class, 'IN');
            }
            if (empty($data['entry_time'])) {
                $data['entry_time'] = now()->format('H:i:s');
            }
            if (!isset($data['status'])) {
                $data['status'] = 'Draft';
            }
        }
        $income->fill($data)->save();
    }

    protected function incomePayload(Income $income): array
    {
        return [
            'id' => $income->id,
            'reference_number' => $income->reference_number,
            'entry_date' => $income->entry_date?->format('Y-m-d'),
            'entry_date_formatted' => $this->formatDate($income->entry_date),
            'entry_time' => $income->entry_time,
            'entry_time_formatted' => $this->formatTime($income->entry_time),
            'description' => $income->description,
            'income_to' => $income->income_to,
            'account_id' => $income->account_id,
            'amount' => (float) $income->amount,
            'amount_formatted' => $this->money($income->amount),
            'status' => $income->status,
        ];
    }

    protected function applyIncomeBalance(Income $income, int $direction): void
    {
        if (!$income->account_id) {
            return;
        }
        $account = Account::find($income->account_id);
        if (!$account) {
            return;
        }
        $account->balance = (float) $account->balance + ((float) $income->amount * $direction);
        $account->save();
    }

    protected function persistTransfer(Request $request, MoneyTransfer $transfer): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'reference_number' => 'nullable|string|max:100',
            'entry_date' => 'required|date',
            'entry_time' => 'nullable',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0',
            'transfer_from' => 'nullable|string|max:255',
            'transfer_to' => 'nullable|string|max:255',
            'from_account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'to_account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'status' => 'sometimes|in:Draft,Approved',
        ]);

        if (!empty($data['from_account_id'])) {
            $data['transfer_from'] = Account::where('company_id', $companyId)->where('id', $data['from_account_id'])->value('name')
                ?? ($data['transfer_from'] ?? null);
        }
        if (!empty($data['to_account_id'])) {
            $data['transfer_to'] = Account::where('company_id', $companyId)->where('id', $data['to_account_id'])->value('name')
                ?? ($data['transfer_to'] ?? null);
        }

        $data['company_id'] = $companyId;
        if (!$transfer->exists) {
            if (empty($data['reference_number'])) {
                $data['reference_number'] = $this->nextDocReference($companyId, MoneyTransfer::class, 'TR');
            }
            if (empty($data['entry_time'])) {
                $data['entry_time'] = now()->format('H:i:s');
            }
            if (!isset($data['status'])) {
                $data['status'] = 'Draft';
            }
        }
        $transfer->fill($data)->save();
    }

    protected function transferPayload(MoneyTransfer $transfer): array
    {
        return [
            'id' => $transfer->id,
            'reference_number' => $transfer->reference_number,
            'entry_date' => $transfer->entry_date?->format('Y-m-d'),
            'entry_date_formatted' => $this->formatDate($transfer->entry_date),
            'entry_time' => $transfer->entry_time,
            'entry_time_formatted' => $this->formatTime($transfer->entry_time),
            'description' => $transfer->description,
            'transfer_from' => $transfer->transfer_from,
            'transfer_to' => $transfer->transfer_to,
            'from_account_id' => $transfer->from_account_id,
            'to_account_id' => $transfer->to_account_id,
            'amount' => (float) $transfer->amount,
            'amount_formatted' => $this->money($transfer->amount),
            'status' => $transfer->status,
        ];
    }

    protected function applyTransferBalance(MoneyTransfer $transfer, int $direction): void
    {
        if (!$transfer->from_account_id || !$transfer->to_account_id) {
            return;
        }
        $from = Account::find($transfer->from_account_id);
        $to = Account::find($transfer->to_account_id);
        if (!$from || !$to) {
            return;
        }
        $amount = (float) $transfer->amount * $direction;
        $from->balance = (float) $from->balance - $amount;
        $to->balance = (float) $to->balance + $amount;
        $from->save();
        $to->save();
    }

    protected function persistRecurringJournal(Request $request, RecurringJournal $recurring): void
    {
        $companyId = $this->requireCompany($request);
        $data = $request->validate([
            'start_date' => 'required|date',
            'account_type' => 'nullable|string|max:100',
            'debit_account' => 'nullable|string|max:255',
            'credit_account' => 'nullable|string|max:255',
            'debit_account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'credit_account_id' => ['nullable', Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'amount' => 'required|numeric|min:0',
            'recurring_interval' => 'nullable|string|max:100',
            'last_transaction_date' => 'nullable|date',
            'approval_status' => 'nullable|string|max:50',
            'operational_status' => 'nullable|string|max:50',
        ]);

        if (!empty($data['debit_account_id'])) {
            $data['debit_account'] = Account::where('company_id', $companyId)->where('id', $data['debit_account_id'])->value('name')
                ?? ($data['debit_account'] ?? null);
        }
        if (!empty($data['credit_account_id'])) {
            $data['credit_account'] = Account::where('company_id', $companyId)->where('id', $data['credit_account_id'])->value('name')
                ?? ($data['credit_account'] ?? null);
        }

        $data['company_id'] = $companyId;
        $recurring->fill($data)->save();
    }

    protected function recurringJournalPayload(RecurringJournal $recurring): array
    {
        return [
            'id' => $recurring->id,
            'start_date' => $recurring->start_date?->format('Y-m-d'),
            'start_date_formatted' => $this->formatDate($recurring->start_date),
            'account_type' => $recurring->account_type,
            'debit_account' => $recurring->debit_account,
            'credit_account' => $recurring->credit_account,
            'debit_account_id' => $recurring->debit_account_id,
            'credit_account_id' => $recurring->credit_account_id,
            'amount' => (float) $recurring->amount,
            'amount_formatted' => $this->money($recurring->amount),
            'recurring_interval' => $recurring->recurring_interval,
            'last_transaction_date' => $recurring->last_transaction_date?->format('Y-m-d'),
            'last_transaction_date_formatted' => $this->formatDate($recurring->last_transaction_date),
            'approval_status' => $recurring->approval_status,
            'operational_status' => $recurring->operational_status,
        ];
    }

    protected function updateEntryStatus($model, Request $request, callable $onApprove, callable $onDraft, callable $payload)
    {
        $data = $request->validate(['status' => 'required|in:Draft,Approved']);
        $newStatus = $data['status'];
        $oldStatus = $model->status;

        if ($newStatus === $oldStatus) {
            return ApiResponse::success($payload($model), 'Status unchanged');
        }

        DB::transaction(function () use ($model, $newStatus, $oldStatus, $onApprove, $onDraft) {
            if ($newStatus === 'Approved' && $oldStatus === 'Draft') {
                $onApprove($model);
            } elseif ($newStatus === 'Draft' && $oldStatus === 'Approved') {
                $onDraft($model);
            }
            $model->update(['status' => $newStatus]);
        });

        return ApiResponse::success($payload($model->fresh()), 'Status updated');
    }
}
