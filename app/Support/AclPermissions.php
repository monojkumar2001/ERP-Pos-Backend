<?php

namespace App\Support;

class AclPermissions
{
    public const GROUPS = [
        'contacts' => [
            'title' => 'Contacts',
            'rows' => ['Suppliers', 'Supplier Type', 'Customers', 'Customer Type'],
        ],
        'items' => [
            'title' => 'Items',
            'rows' => [
                'Attribute Set', 'Brand', 'Category', 'Color', 'Items',
                'Manufacturer', 'Size Type', 'Price List',
            ],
        ],
        'sales' => [
            'title' => 'Sales',
            'rows' => [
                'All Invoices', 'Delivery', 'Quotation', 'Order', 'Return',
                'Payment', 'Direct Invoice', 'Batch Payment', 'Customer Debit', 'Refund',
            ],
        ],
        'purchases' => [
            'title' => 'Purchases',
            'rows' => [
                'All Bills', 'Payment', 'Direct Bill', 'Order', 'Receipt',
                'Requisition', 'Return', 'Batch Payment', 'Supplier Credit', 'Refund',
            ],
        ],
        'inventory' => [
            'title' => 'Inventory',
            'rows' => [
                'Stock Adjustment', 'Stock Movement', 'Item Stock Summary',
                'Receivable Stock', 'Warehouse Access',
            ],
        ],
        'accounting' => [
            'title' => 'Accounting',
            'rows' => [
                'Chart Of Account', 'Incomes', 'Expenses', 'Journals',
                'Internal Transfer', 'Recurring Journals',
            ],
        ],
        'pos' => [
            'title' => 'Point Of Sale',
            'rows' => ['Outlet', 'Counter', 'Orders', 'Barcode', 'Outlet Access'],
        ],
        'hrm' => [
            'title' => 'HR & Payroll',
            'rows' => ['Employees', 'Attendance', 'Payroll', 'Leave', 'Holidays'],
        ],
        'acl' => [
            'title' => 'Permission Management',
            'rows' => ['Role', 'User'],
        ],
    ];

    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    public static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value);

        return trim($value, '_');
    }

    public static function key(string $group, string $resource, string $action): string
    {
        return self::slugify($group) . '.' . self::slugify($resource) . '.' . $action;
    }

    public static function matrixFromKeys(iterable $keys): array
    {
        $set = collect($keys)->flip();
        $out = [];
        foreach (self::GROUPS as $groupKey => $group) {
            $out[$groupKey] = [];
            foreach ($group['rows'] as $row) {
                $out[$groupKey][$row] = [];
                foreach (self::ACTIONS as $action) {
                    $out[$groupKey][$row][$action] = $set->has(self::key($groupKey, $row, $action));
                }
            }
        }

        return $out;
    }

    public static function keysFromMatrix(array $matrix): array
    {
        $keys = [];
        foreach ($matrix as $groupKey => $rows) {
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $resource => $actions) {
                if (!is_array($actions)) {
                    continue;
                }
                foreach (self::ACTIONS as $action) {
                    if (!empty($actions[$action])) {
                        $keys[] = self::key($groupKey, $resource, $action);
                    }
                }
            }
        }

        return array_values(array_unique($keys));
    }
}
