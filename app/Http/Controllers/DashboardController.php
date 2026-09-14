<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BelongsToCompany;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use BelongsToCompany;

    /**
     * Business dashboard KPIs matching Frontend DashboardHome + live Biznify layout.
     * Values are company-scoped demo snapshots until sales/POS/purchase tables exist.
     */
    public function home(Request $request)
    {
        $this->requireCompany($request);

        return ApiResponse::success([
            'sales' => $this->periodRows([
                ['Today', '503,215', 'Yesterday', '3,060,800', '608%', 'down'],
                ['This Week', '3,564,015', 'Last Week', '29,560', '1000+%', 'up'],
                ['This Month', '3,601,859.83', 'Last Month', '640,126.55', '563%', 'up'],
                ['This Year', '38,349,021.4', 'Last Year', '47,558,256.78', '124%', 'down'],
            ]),
            'pos' => $this->periodRows([
                ['Today', '0', 'Yesterday', '5,500', '0%', 'down'],
                ['This Week', '5,500', 'Last Week', '3,650', '151%', 'up'],
                ['This Month', '9,150', 'Last Month', '432,931', '1000+%', 'down'],
                ['This Year', '814,060.18', 'Last Year', '819,809.15', '101%', 'down'],
            ]),
            'purchase' => $this->periodRows([
                ['Today', '529', 'Yesterday', '900,222', '1000+%', 'down'],
                ['This Week', '902,951', 'Last Week', '223,550', '404%', 'up'],
                ['This Month', '1,257,601', 'Last Month', '1,266,282.02', '101%', 'down'],
                ['This Year', '123,151,651.73', 'Last Year', '22,862,214,773.07', '1000+%', 'up'],
            ]),
            'receivables' => [
                'total' => '৳ 36,673,590.49',
                'left_title' => 'Total Unpaid Invoices',
                'left_rows' => [
                    ['Current (This Month)', '৳ 1,995'],
                    ['Overdue (Previous Months)', '৳ 36,671,595.49'],
                ],
            ],
            'payables' => [
                'total' => '৳ 22,019,865,121.59',
                'left_title' => 'Total Unpaid Bills',
                'left_rows' => [
                    ['Current (This Month)', '৳ 117,200'],
                    ['Overdue (Previous Months)', '৳ 22,019,747,921.59'],
                ],
            ],
            'sales_returnable' => [
                'total' => '৳ 7,501,734.12',
                'left_title' => 'Total Payable Memos',
                'left_rows' => [
                    ['Current (This Month)', '৳ 0'],
                    ['Previous (Till Previous Month)', '৳ 7,501,734.12'],
                ],
                'right_total' => '৳ 2,451,065.22',
                'right_title' => 'Total Applied Memos',
                'right_rows' => [
                    ['Current (This Month)', '৳ 0'],
                    ['Previous (Till Previous Month)', '৳ 2,451,065.22'],
                ],
            ],
            'purchase_returnable' => [
                'total' => '৳ 554,428',
                'left_title' => 'Total Receivable Memos',
                'left_rows' => [
                    ['Current (This Month)', '৳ 0'],
                    ['Previous (Till Previous Month)', '৳ 554,428'],
                ],
                'right_total' => '৳ 1,507,498',
                'right_title' => 'Total Applied Memos',
                'right_rows' => [
                    ['Current (This Month)', '৳ 0'],
                    ['Previous (Till Previous Month)', '৳ 1,507,498'],
                ],
            ],
            'item_details' => [
                ['label' => 'Low Stock Items', 'value' => '247', 'color' => '#f55151'],
                ['label' => 'All Active Items', 'value' => '4,726', 'color' => '#00c278'],
                ['label' => 'All Item Groups', 'value' => '142', 'color' => '#4ca6ff'],
                ['label' => 'All Items', 'value' => '4,756', 'color' => '#6a5acd'],
            ],
            'top_selling_items' => [
                ['name' => 'TP Link 1pc', 'qty' => '128'],
                ['name' => 'Rice 25kg', 'qty' => '96'],
                ['name' => 'Soybean Oil 5L', 'qty' => '84'],
                ['name' => 'Sugar 1kg', 'qty' => '71'],
                ['name' => 'Flour 10kg', 'qty' => '63'],
            ],
            'hrm' => [
                'attendance' => [
                    ['label' => 'Present', 'value' => 70, 'color' => '#00c278'],
                    ['label' => 'Absent', 'value' => 8, 'color' => '#f55151'],
                    ['label' => 'Late', 'value' => 12, 'color' => '#ffa34d'],
                    ['label' => 'EarlyOut', 'value' => 5, 'color' => '#4ca6ff'],
                    ['label' => 'Weekend', 'value' => 0, 'color' => '#9b59b6'],
                    ['label' => 'Holiday', 'value' => 0, 'color' => '#95a5a6'],
                ],
                'departments' => [
                    ['department' => 'Sales', 'present' => 18, 'absent' => 1, 'late' => 2, 'early_out' => 0, 'weekend' => 0, 'holiday' => 0],
                    ['department' => 'Accounts', 'present' => 8, 'absent' => 0, 'late' => 1, 'early_out' => 0, 'weekend' => 0, 'holiday' => 0],
                    ['department' => 'Warehouse', 'present' => 14, 'absent' => 2, 'late' => 3, 'early_out' => 1, 'weekend' => 0, 'holiday' => 0],
                    ['department' => 'HR', 'present' => 5, 'absent' => 0, 'late' => 0, 'early_out' => 0, 'weekend' => 0, 'holiday' => 0],
                    ['department' => 'Production', 'present' => 25, 'absent' => 5, 'late' => 6, 'early_out' => 4, 'weekend' => 0, 'holiday' => 0],
                ],
            ],
        ], 'Business dashboard');
    }

    protected function periodRows(array $rows): array
    {
        return array_map(function (array $row) {
            return [
                'currentLabel' => $row[0],
                'currentValue' => $row[1],
                'prevLabel' => $row[2],
                'prevValue' => $row[3],
                'percent' => $row[4],
                'trend' => $row[5],
            ];
        }, $rows);
    }
}
