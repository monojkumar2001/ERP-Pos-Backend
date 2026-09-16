<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AclRoleController;
use App\Http\Controllers\AclUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AttributeSetController;
use App\Http\Controllers\BinLocationController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ManufacturerController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PriceListController;
use App\Http\Controllers\SizeTypeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierTypeController;
use App\Http\Controllers\UomConversionController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\Settings\AdjustmentReasonController;
use App\Http\Controllers\Settings\CompanySettingController;
use App\Http\Controllers\Settings\CustomFieldController;
use App\Http\Controllers\Settings\DeliveryChannelController;
use App\Http\Controllers\Settings\DeliveryChargeController;
use App\Http\Controllers\Settings\DiscountController;
use App\Http\Controllers\Settings\EmailTemplateController;
use App\Http\Controllers\Settings\PaymentMethodController;
use App\Http\Controllers\Settings\SmsTemplateController;
use App\Http\Controllers\Settings\TaxController;

// Public auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/verify', [AuthController::class, 'verify']);
Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/update/user', [UserController::class, 'updateUser']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('company')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'home']);
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);

        Route::prefix('accounting')->group(function () {
            Route::get('/dashboard', [AccountingController::class, 'dashboard']);
            Route::get('/accounts', [AccountingController::class, 'accounts']);
            Route::post('/accounts', [AccountingController::class, 'storeAccount']);
            Route::post('/accounts/reorder', [AccountingController::class, 'reorderAccounts']);
            Route::get('/accounts/{id}', [AccountingController::class, 'showAccount']);
            Route::put('/accounts/{id}', [AccountingController::class, 'updateAccount']);
            Route::delete('/accounts/{id}', [AccountingController::class, 'destroyAccount']);

            Route::get('/journals', [AccountingController::class, 'journals']);
            Route::post('/journals', [AccountingController::class, 'storeJournal']);
            Route::get('/journals/{id}', [AccountingController::class, 'showJournal']);
            Route::put('/journals/{id}', [AccountingController::class, 'updateJournal']);
            Route::delete('/journals/{id}', [AccountingController::class, 'destroyJournal']);
            Route::patch('/journals/{id}/status', [AccountingController::class, 'journalStatus']);
            Route::patch('/journals/{id}/approve', [AccountingController::class, 'approveJournalEntry']);

            Route::get('/expenses', [AccountingController::class, 'expenses']);
            Route::post('/expenses', [AccountingController::class, 'storeExpense']);
            Route::get('/expenses/{id}', [AccountingController::class, 'showExpense']);
            Route::put('/expenses/{id}', [AccountingController::class, 'updateExpense']);
            Route::delete('/expenses/{id}', [AccountingController::class, 'destroyExpense']);
            Route::patch('/expenses/{id}/status', [AccountingController::class, 'expenseStatus']);

            Route::get('/incomes', [AccountingController::class, 'incomes']);
            Route::post('/incomes', [AccountingController::class, 'storeIncome']);
            Route::get('/incomes/{id}', [AccountingController::class, 'showIncome']);
            Route::put('/incomes/{id}', [AccountingController::class, 'updateIncome']);
            Route::delete('/incomes/{id}', [AccountingController::class, 'destroyIncome']);
            Route::patch('/incomes/{id}/status', [AccountingController::class, 'incomeStatus']);

            Route::get('/transfers', [AccountingController::class, 'transfers']);
            Route::post('/transfers', [AccountingController::class, 'storeTransfer']);
            Route::get('/transfers/{id}', [AccountingController::class, 'showTransfer']);
            Route::put('/transfers/{id}', [AccountingController::class, 'updateTransfer']);
            Route::delete('/transfers/{id}', [AccountingController::class, 'destroyTransfer']);
            Route::patch('/transfers/{id}/status', [AccountingController::class, 'transferStatus']);

            Route::get('/recurring-journals', [AccountingController::class, 'recurringJournals']);
            Route::post('/recurring-journals', [AccountingController::class, 'storeRecurringJournal']);
            Route::get('/recurring-journals/{id}', [AccountingController::class, 'showRecurringJournal']);
            Route::put('/recurring-journals/{id}', [AccountingController::class, 'updateRecurringJournal']);
            Route::delete('/recurring-journals/{id}', [AccountingController::class, 'destroyRecurringJournal']);
            Route::post('/recurring-journals/{id}/run', [AccountingController::class, 'runRecurringJournal']);
        });

        Route::prefix('acl')->group(function () {
            Route::get('/permissions', [AclRoleController::class, 'permissionCatalog']);

            Route::get('/roles', [AclRoleController::class, 'index']);
            Route::post('/roles', [AclRoleController::class, 'store']);
            Route::get('/roles/{id}', [AclRoleController::class, 'show']);
            Route::put('/roles/{id}', [AclRoleController::class, 'update']);
            Route::delete('/roles/{id}', [AclRoleController::class, 'destroy']);
            Route::post('/roles/{id}/clone', [AclRoleController::class, 'clone']);
            Route::post('/roles/{id}/invite-user', [AclRoleController::class, 'inviteUser']);

            Route::get('/users', [AclUserController::class, 'index']);
            Route::post('/users/invite', [AclUserController::class, 'invite']);
            Route::get('/users/{id}', [AclUserController::class, 'show']);
            Route::patch('/users/{id}/status', [AclUserController::class, 'updateStatus']);
            Route::post('/users/{id}/roles', [AclUserController::class, 'assignRole']);
        });

        Route::prefix('master')->group(function () {
            Route::get('/item-categories', [ItemCategoryController::class, 'index']);
            Route::post('/item-categories', [ItemCategoryController::class, 'store']);
            Route::get('/item-categories/{id}', [ItemCategoryController::class, 'show']);
            Route::put('/item-categories/{id}', [ItemCategoryController::class, 'update']);
            Route::delete('/item-categories/{id}', [ItemCategoryController::class, 'destroy']);
            Route::patch('/item-categories/{id}/status', [ItemCategoryController::class, 'status']);

            Route::get('/manufacturers', [ManufacturerController::class, 'index']);
            Route::post('/manufacturers', [ManufacturerController::class, 'store']);
            Route::get('/manufacturers/{id}', [ManufacturerController::class, 'show']);
            Route::put('/manufacturers/{id}', [ManufacturerController::class, 'update']);
            Route::delete('/manufacturers/{id}', [ManufacturerController::class, 'destroy']);
            Route::patch('/manufacturers/{id}/status', [ManufacturerController::class, 'status']);
            Route::patch('/manufacturers/{id}/discount', [ManufacturerController::class, 'discount']);

            Route::get('/brands', [BrandController::class, 'index']);
            Route::post('/brands', [BrandController::class, 'store']);
            Route::get('/brands/{id}', [BrandController::class, 'show']);
            Route::put('/brands/{id}', [BrandController::class, 'update']);
            Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
            Route::patch('/brands/{id}/status', [BrandController::class, 'status']);
            Route::patch('/brands/{id}/discount', [BrandController::class, 'discount']);

            Route::get('/colors', [ColorController::class, 'index']);
            Route::post('/colors', [ColorController::class, 'store']);
            Route::get('/colors/{id}', [ColorController::class, 'show']);
            Route::put('/colors/{id}', [ColorController::class, 'update']);
            Route::delete('/colors/{id}', [ColorController::class, 'destroy']);
            Route::patch('/colors/{id}/status', [ColorController::class, 'status']);

            Route::get('/size-types', [SizeTypeController::class, 'index']);
            Route::post('/size-types', [SizeTypeController::class, 'store']);
            Route::get('/size-types/{id}', [SizeTypeController::class, 'show']);
            Route::put('/size-types/{id}', [SizeTypeController::class, 'update']);
            Route::delete('/size-types/{id}', [SizeTypeController::class, 'destroy']);
            Route::patch('/size-types/{id}/status', [SizeTypeController::class, 'status']);

            Route::get('/attribute-sets', [AttributeSetController::class, 'index']);
            Route::post('/attribute-sets', [AttributeSetController::class, 'store']);
            Route::get('/attribute-sets/{id}', [AttributeSetController::class, 'show']);
            Route::put('/attribute-sets/{id}', [AttributeSetController::class, 'update']);
            Route::delete('/attribute-sets/{id}', [AttributeSetController::class, 'destroy']);
            Route::patch('/attribute-sets/{id}/status', [AttributeSetController::class, 'status']);

            Route::get('/price-lists', [PriceListController::class, 'index']);
            Route::post('/price-lists', [PriceListController::class, 'store']);
            Route::get('/price-lists/{id}', [PriceListController::class, 'show']);
            Route::put('/price-lists/{id}', [PriceListController::class, 'update']);
            Route::delete('/price-lists/{id}', [PriceListController::class, 'destroy']);
            Route::patch('/price-lists/{id}/status', [PriceListController::class, 'status']);
            Route::patch('/price-lists/{id}/deactivate', [PriceListController::class, 'deactivate']);

            Route::get('/uom-conversions', [UomConversionController::class, 'index']);
            Route::post('/uom-conversions', [UomConversionController::class, 'store']);
            Route::get('/uom-conversions/{id}', [UomConversionController::class, 'show']);
            Route::put('/uom-conversions/{id}', [UomConversionController::class, 'update']);
            Route::delete('/uom-conversions/{id}', [UomConversionController::class, 'destroy']);
            Route::patch('/uom-conversions/{id}/status', [UomConversionController::class, 'status']);

            Route::get('/items', [ItemController::class, 'index']);
            Route::post('/items', [ItemController::class, 'store']);
            Route::get('/items/{id}', [ItemController::class, 'show']);
            Route::put('/items/{id}', [ItemController::class, 'update']);
            Route::delete('/items/{id}', [ItemController::class, 'destroy']);
            Route::patch('/items/{id}/status', [ItemController::class, 'status']);
            Route::patch('/items/{id}/opening-stock', [ItemController::class, 'openingStock']);

            Route::get('/customer-types', [CustomerTypeController::class, 'index']);
            Route::post('/customer-types', [CustomerTypeController::class, 'store']);
            Route::put('/customer-types/{id}', [CustomerTypeController::class, 'update']);
            Route::delete('/customer-types/{id}', [CustomerTypeController::class, 'destroy']);
            Route::patch('/customer-types/{id}/status', [CustomerTypeController::class, 'status']);

            Route::get('/customers', [CustomerController::class, 'index']);
            Route::post('/customers', [CustomerController::class, 'store']);
            Route::get('/customers/{id}', [CustomerController::class, 'show']);
            Route::put('/customers/{id}', [CustomerController::class, 'update']);
            Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);
            Route::patch('/customers/{id}/status', [CustomerController::class, 'status']);

            Route::get('/supplier-types', [SupplierTypeController::class, 'index']);
            Route::post('/supplier-types', [SupplierTypeController::class, 'store']);
            Route::put('/supplier-types/{id}', [SupplierTypeController::class, 'update']);
            Route::delete('/supplier-types/{id}', [SupplierTypeController::class, 'destroy']);
            Route::patch('/supplier-types/{id}/status', [SupplierTypeController::class, 'status']);

            Route::get('/suppliers', [SupplierController::class, 'index']);
            Route::post('/suppliers', [SupplierController::class, 'store']);
            Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
            Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
            Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);
            Route::patch('/suppliers/{id}/status', [SupplierController::class, 'status']);

            Route::get('/warehouses', [WarehouseController::class, 'index']);
            Route::post('/warehouses', [WarehouseController::class, 'store']);
            Route::get('/warehouses/{id}', [WarehouseController::class, 'show']);
            Route::put('/warehouses/{id}', [WarehouseController::class, 'update']);
            Route::delete('/warehouses/{id}', [WarehouseController::class, 'destroy']);
            Route::patch('/warehouses/{id}/status', [WarehouseController::class, 'status']);

            Route::get('/bin-locations', [BinLocationController::class, 'index']);
            Route::post('/bin-locations', [BinLocationController::class, 'store']);
            Route::get('/bin-locations/{id}', [BinLocationController::class, 'show']);
            Route::put('/bin-locations/{id}', [BinLocationController::class, 'update']);
            Route::delete('/bin-locations/{id}', [BinLocationController::class, 'destroy']);
            Route::patch('/bin-locations/{id}/status', [BinLocationController::class, 'status']);

            Route::get('/outlets', [OutletController::class, 'index']);
            Route::post('/outlets', [OutletController::class, 'store']);
            Route::get('/outlets/{id}', [OutletController::class, 'show']);
            Route::put('/outlets/{id}', [OutletController::class, 'update']);
            Route::delete('/outlets/{id}', [OutletController::class, 'destroy']);
            Route::patch('/outlets/{id}/status', [OutletController::class, 'status']);
        });

        Route::prefix('inventory')->group(function () {
            Route::get('/item-stock', [InventoryController::class, 'itemStock']);

            Route::get('/stock-adjustments', [InventoryController::class, 'stockAdjustments']);
            Route::post('/stock-adjustments', [InventoryController::class, 'storeStockAdjustment']);
            Route::get('/stock-adjustments/{id}', [InventoryController::class, 'showStockAdjustment']);
            Route::put('/stock-adjustments/{id}', [InventoryController::class, 'updateStockAdjustment']);
            Route::delete('/stock-adjustments/{id}', [InventoryController::class, 'destroyStockAdjustment']);
            Route::patch('/stock-adjustments/{id}/cancel', [InventoryController::class, 'cancelStockAdjustment']);
            Route::patch('/stock-adjustments/{id}/status', [InventoryController::class, 'stockAdjustmentStatus']);

            Route::get('/warehouse-transfers', [InventoryController::class, 'warehouseTransfers']);
            Route::post('/warehouse-transfers', [InventoryController::class, 'storeWarehouseTransfer']);
            Route::post('/warehouse-transfers/request', [InventoryController::class, 'requestWarehouseTransfer']);
            Route::get('/warehouse-transfers/{id}', [InventoryController::class, 'showWarehouseTransfer']);
            Route::put('/warehouse-transfers/{id}', [InventoryController::class, 'updateWarehouseTransfer']);
            Route::post('/warehouse-transfers/{id}/initiate', [InventoryController::class, 'initiateWarehouseTransfer']);
            Route::post('/warehouse-transfers/{id}/receive', [InventoryController::class, 'receiveWarehouseTransfer']);
            Route::patch('/warehouse-transfers/{id}/cancel', [InventoryController::class, 'cancelWarehouseTransfer']);

            Route::get('/receivable-stock', [InventoryController::class, 'receivableStock']);

            Route::get('/warehouse-access', [InventoryController::class, 'warehouseAccess']);
            Route::post('/warehouse-access', [InventoryController::class, 'storeWarehouseAccess']);
            Route::delete('/warehouse-access/{id}', [InventoryController::class, 'destroyWarehouseAccess']);
        });

        Route::prefix('sales')->group(function () {
            Route::get('/direct-invoices', [SalesController::class, 'directInvoices']);
            Route::post('/direct-invoices', [SalesController::class, 'storeDirectInvoice']);
            Route::get('/direct-invoices/{id}', [SalesController::class, 'showDirectInvoice']);
            Route::put('/direct-invoices/{id}', [SalesController::class, 'updateDirectInvoice']);
            Route::delete('/direct-invoices/{id}', [SalesController::class, 'destroyDirectInvoice']);
            Route::patch('/direct-invoices/{id}/status', [SalesController::class, 'directInvoiceStatus']);
            Route::patch('/direct-invoices/{id}/cancel', [SalesController::class, 'cancelDirectInvoice']);

            Route::get('/quotations', [SalesController::class, 'quotations']);
            Route::post('/quotations', [SalesController::class, 'storeQuotation']);
            Route::get('/quotations/{id}', [SalesController::class, 'showQuotation']);
            Route::put('/quotations/{id}', [SalesController::class, 'updateQuotation']);
            Route::delete('/quotations/{id}', [SalesController::class, 'destroyQuotation']);
            Route::patch('/quotations/{id}/status', [SalesController::class, 'quotationStatus']);
            Route::post('/quotations/{id}/convert-to-order', [SalesController::class, 'convertQuotationToOrder']);

            Route::get('/orders', [SalesController::class, 'orders']);
            Route::post('/orders', [SalesController::class, 'storeOrder']);
            Route::get('/orders/{id}', [SalesController::class, 'showOrder']);
            Route::put('/orders/{id}', [SalesController::class, 'updateOrder']);
            Route::delete('/orders/{id}', [SalesController::class, 'destroyOrder']);
            Route::patch('/orders/{id}/status', [SalesController::class, 'orderStatus']);
            Route::post('/orders/{id}/convert-to-delivery', [SalesController::class, 'convertOrderToDelivery']);
            Route::post('/orders/{id}/convert-to-invoice', [SalesController::class, 'convertOrderToInvoice']);

            Route::get('/deliveries', [SalesController::class, 'deliveries']);
            Route::post('/deliveries', [SalesController::class, 'storeDelivery']);
            Route::get('/deliveries/{id}', [SalesController::class, 'showDelivery']);
            Route::put('/deliveries/{id}', [SalesController::class, 'updateDelivery']);
            Route::delete('/deliveries/{id}', [SalesController::class, 'destroyDelivery']);
            Route::patch('/deliveries/{id}/status', [SalesController::class, 'deliveryStatus']);

            Route::get('/invoices', [SalesController::class, 'invoices']);
            Route::post('/invoices', [SalesController::class, 'storeInvoice']);
            Route::get('/invoices/{id}', [SalesController::class, 'showInvoice']);
            Route::put('/invoices/{id}', [SalesController::class, 'updateInvoice']);
            Route::delete('/invoices/{id}', [SalesController::class, 'destroyInvoice']);
            Route::patch('/invoices/{id}/status', [SalesController::class, 'invoiceStatus']);

            Route::get('/returns', [SalesController::class, 'returns']);
            Route::post('/returns', [SalesController::class, 'storeReturn']);
            Route::get('/returns/{id}', [SalesController::class, 'showReturn']);
            Route::put('/returns/{id}', [SalesController::class, 'updateReturn']);
            Route::delete('/returns/{id}', [SalesController::class, 'destroyReturn']);
            Route::patch('/returns/{id}/status', [SalesController::class, 'returnStatus']);

            Route::get('/batch-payments', [SalesController::class, 'batchPayments']);
            Route::post('/batch-payments', [SalesController::class, 'storeBatchPayment']);
            Route::get('/batch-payments/{id}', [SalesController::class, 'showBatchPayment']);
            Route::put('/batch-payments/{id}', [SalesController::class, 'updateBatchPayment']);
            Route::delete('/batch-payments/{id}', [SalesController::class, 'destroyBatchPayment']);
            Route::patch('/batch-payments/{id}/status', [SalesController::class, 'batchPaymentStatus']);

            Route::get('/payments', [SalesController::class, 'payments']);
            Route::post('/payments', [SalesController::class, 'storePayment']);
            Route::get('/payments/{id}', [SalesController::class, 'showPayment']);
            Route::put('/payments/{id}', [SalesController::class, 'updatePayment']);
            Route::delete('/payments/{id}', [SalesController::class, 'destroyPayment']);
            Route::patch('/payments/{id}/status', [SalesController::class, 'paymentStatus']);

            Route::get('/customer-debits', [SalesController::class, 'customerDebits']);
            Route::post('/customer-debits', [SalesController::class, 'storeCustomerDebit']);
            Route::get('/customer-debits/{id}', [SalesController::class, 'showCustomerDebit']);
            Route::put('/customer-debits/{id}', [SalesController::class, 'updateCustomerDebit']);
            Route::delete('/customer-debits/{id}', [SalesController::class, 'destroyCustomerDebit']);
            Route::patch('/customer-debits/{id}/status', [SalesController::class, 'customerDebitStatus']);

            Route::get('/refunds', [SalesController::class, 'refunds']);
            Route::post('/refunds', [SalesController::class, 'storeRefund']);
            Route::get('/refunds/{id}', [SalesController::class, 'showRefund']);
            Route::put('/refunds/{id}', [SalesController::class, 'updateRefund']);
            Route::delete('/refunds/{id}', [SalesController::class, 'destroyRefund']);
            Route::patch('/refunds/{id}/status', [SalesController::class, 'refundStatus']);
        });

        Route::prefix('purchase')->group(function () {
            Route::get('/direct-bills', [PurchaseController::class, 'directBills']);
            Route::post('/direct-bills', [PurchaseController::class, 'storeDirectBill']);
            Route::get('/direct-bills/{id}', [PurchaseController::class, 'showDirectBill']);
            Route::put('/direct-bills/{id}', [PurchaseController::class, 'updateDirectBill']);
            Route::delete('/direct-bills/{id}', [PurchaseController::class, 'destroyDirectBill']);
            Route::patch('/direct-bills/{id}/status', [PurchaseController::class, 'directBillStatus']);
            Route::patch('/direct-bills/{id}/cancel', [PurchaseController::class, 'cancelDirectBill']);

            Route::get('/requisitions', [PurchaseController::class, 'requisitions']);
            Route::post('/requisitions', [PurchaseController::class, 'storeRequisition']);
            Route::get('/requisitions/{id}', [PurchaseController::class, 'showRequisition']);
            Route::put('/requisitions/{id}', [PurchaseController::class, 'updateRequisition']);
            Route::delete('/requisitions/{id}', [PurchaseController::class, 'destroyRequisition']);
            Route::patch('/requisitions/{id}/status', [PurchaseController::class, 'requisitionStatus']);
            Route::post('/requisitions/{id}/convert-to-order', [PurchaseController::class, 'convertRequisitionToOrder']);

            Route::get('/orders', [PurchaseController::class, 'orders']);
            Route::post('/orders', [PurchaseController::class, 'storeOrder']);
            Route::get('/orders/{id}', [PurchaseController::class, 'showOrder']);
            Route::put('/orders/{id}', [PurchaseController::class, 'updateOrder']);
            Route::delete('/orders/{id}', [PurchaseController::class, 'destroyOrder']);
            Route::patch('/orders/{id}/status', [PurchaseController::class, 'orderStatus']);
            Route::post('/orders/{id}/convert-to-receipt', [PurchaseController::class, 'convertOrderToReceipt']);
            Route::post('/orders/{id}/convert-to-bill', [PurchaseController::class, 'convertOrderToBill']);

            Route::get('/receipts', [PurchaseController::class, 'receipts']);
            Route::post('/receipts', [PurchaseController::class, 'storeReceipt']);
            Route::get('/receipts/{id}', [PurchaseController::class, 'showReceipt']);
            Route::put('/receipts/{id}', [PurchaseController::class, 'updateReceipt']);
            Route::delete('/receipts/{id}', [PurchaseController::class, 'destroyReceipt']);
            Route::patch('/receipts/{id}/status', [PurchaseController::class, 'receiptStatus']);

            Route::get('/bills', [PurchaseController::class, 'bills']);
            Route::post('/bills', [PurchaseController::class, 'storeBill']);
            Route::get('/bills/{id}', [PurchaseController::class, 'showBill']);
            Route::put('/bills/{id}', [PurchaseController::class, 'updateBill']);
            Route::delete('/bills/{id}', [PurchaseController::class, 'destroyBill']);
            Route::patch('/bills/{id}/status', [PurchaseController::class, 'billStatus']);

            Route::get('/returns', [PurchaseController::class, 'returns']);
            Route::post('/returns', [PurchaseController::class, 'storeReturn']);
            Route::get('/returns/{id}', [PurchaseController::class, 'showReturn']);
            Route::put('/returns/{id}', [PurchaseController::class, 'updateReturn']);
            Route::delete('/returns/{id}', [PurchaseController::class, 'destroyReturn']);
            Route::patch('/returns/{id}/status', [PurchaseController::class, 'returnStatus']);

            Route::get('/batch-payments', [PurchaseController::class, 'batchPayments']);
            Route::post('/batch-payments', [PurchaseController::class, 'storeBatchPayment']);
            Route::get('/batch-payments/{id}', [PurchaseController::class, 'showBatchPayment']);
            Route::put('/batch-payments/{id}', [PurchaseController::class, 'updateBatchPayment']);
            Route::delete('/batch-payments/{id}', [PurchaseController::class, 'destroyBatchPayment']);
            Route::patch('/batch-payments/{id}/status', [PurchaseController::class, 'batchPaymentStatus']);

            Route::get('/payments', [PurchaseController::class, 'payments']);
            Route::post('/payments', [PurchaseController::class, 'storePayment']);
            Route::get('/payments/{id}', [PurchaseController::class, 'showPayment']);
            Route::put('/payments/{id}', [PurchaseController::class, 'updatePayment']);
            Route::delete('/payments/{id}', [PurchaseController::class, 'destroyPayment']);
            Route::patch('/payments/{id}/status', [PurchaseController::class, 'paymentStatus']);

            Route::get('/supplier-credits', [PurchaseController::class, 'supplierCredits']);
            Route::post('/supplier-credits', [PurchaseController::class, 'storeSupplierCredit']);
            Route::get('/supplier-credits/{id}', [PurchaseController::class, 'showSupplierCredit']);
            Route::put('/supplier-credits/{id}', [PurchaseController::class, 'updateSupplierCredit']);
            Route::delete('/supplier-credits/{id}', [PurchaseController::class, 'destroySupplierCredit']);
            Route::patch('/supplier-credits/{id}/status', [PurchaseController::class, 'supplierCreditStatus']);

            Route::get('/refunds', [PurchaseController::class, 'refunds']);
            Route::post('/refunds', [PurchaseController::class, 'storeRefund']);
            Route::get('/refunds/{id}', [PurchaseController::class, 'showRefund']);
            Route::put('/refunds/{id}', [PurchaseController::class, 'updateRefund']);
            Route::delete('/refunds/{id}', [PurchaseController::class, 'destroyRefund']);
            Route::patch('/refunds/{id}/status', [PurchaseController::class, 'refundStatus']);
        });

        Route::prefix('settings')->group(function () {
            Route::get('/company', [CompanySettingController::class, 'index']);
            Route::put('/company', [CompanySettingController::class, 'update']);

            Route::get('/taxes', [TaxController::class, 'index']);
            Route::post('/taxes', [TaxController::class, 'store']);
            Route::get('/taxes/{id}', [TaxController::class, 'show']);
            Route::put('/taxes/{id}', [TaxController::class, 'update']);
            Route::delete('/taxes/{id}', [TaxController::class, 'destroy']);
            Route::patch('/taxes/{id}/status', [TaxController::class, 'status']);

            Route::get('/discounts', [DiscountController::class, 'index']);
            Route::post('/discounts', [DiscountController::class, 'store']);
            Route::get('/discounts/{id}', [DiscountController::class, 'show']);
            Route::put('/discounts/{id}', [DiscountController::class, 'update']);
            Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);
            Route::patch('/discounts/{id}/status', [DiscountController::class, 'status']);
            Route::patch('/discounts/{id}/effective-date', [DiscountController::class, 'effectiveDate']);

            Route::get('/payment-methods', [PaymentMethodController::class, 'index']);
            Route::post('/payment-methods', [PaymentMethodController::class, 'store']);
            Route::get('/payment-methods/{id}', [PaymentMethodController::class, 'show']);
            Route::put('/payment-methods/{id}', [PaymentMethodController::class, 'update']);
            Route::delete('/payment-methods/{id}', [PaymentMethodController::class, 'destroy']);
            Route::patch('/payment-methods/{id}/status', [PaymentMethodController::class, 'status']);

            Route::get('/delivery-channels', [DeliveryChannelController::class, 'index']);
            Route::post('/delivery-channels', [DeliveryChannelController::class, 'store']);
            Route::get('/delivery-channels/{id}', [DeliveryChannelController::class, 'show']);
            Route::put('/delivery-channels/{id}', [DeliveryChannelController::class, 'update']);
            Route::delete('/delivery-channels/{id}', [DeliveryChannelController::class, 'destroy']);
            Route::patch('/delivery-channels/{id}/status', [DeliveryChannelController::class, 'status']);

            Route::get('/delivery-charges', [DeliveryChargeController::class, 'index']);
            Route::post('/delivery-charges', [DeliveryChargeController::class, 'store']);
            Route::post('/delivery-charges/generate', [DeliveryChargeController::class, 'generate']);
            Route::get('/delivery-charges/{id}', [DeliveryChargeController::class, 'show']);
            Route::put('/delivery-charges/{id}', [DeliveryChargeController::class, 'update']);
            Route::delete('/delivery-charges/{id}', [DeliveryChargeController::class, 'destroy']);
            Route::patch('/delivery-charges/{id}/status', [DeliveryChargeController::class, 'status']);

            Route::get('/adjustment-reasons', [AdjustmentReasonController::class, 'index']);
            Route::post('/adjustment-reasons', [AdjustmentReasonController::class, 'store']);
            Route::get('/adjustment-reasons/{id}', [AdjustmentReasonController::class, 'show']);
            Route::put('/adjustment-reasons/{id}', [AdjustmentReasonController::class, 'update']);
            Route::delete('/adjustment-reasons/{id}', [AdjustmentReasonController::class, 'destroy']);
            Route::patch('/adjustment-reasons/{id}/status', [AdjustmentReasonController::class, 'status']);

            Route::get('/custom-fields', [CustomFieldController::class, 'index']);
            Route::post('/custom-fields', [CustomFieldController::class, 'store']);
            Route::get('/custom-fields/{id}', [CustomFieldController::class, 'show']);
            Route::put('/custom-fields/{id}', [CustomFieldController::class, 'update']);
            Route::delete('/custom-fields/{id}', [CustomFieldController::class, 'destroy']);
            Route::patch('/custom-fields/{id}/status', [CustomFieldController::class, 'status']);

            Route::get('/email-templates', [EmailTemplateController::class, 'index']);
            Route::post('/email-templates', [EmailTemplateController::class, 'store']);
            Route::get('/email-templates/{id}', [EmailTemplateController::class, 'show']);
            Route::put('/email-templates/{id}', [EmailTemplateController::class, 'update']);
            Route::delete('/email-templates/{id}', [EmailTemplateController::class, 'destroy']);
            Route::patch('/email-templates/{id}/status', [EmailTemplateController::class, 'status']);

            Route::get('/sms-templates', [SmsTemplateController::class, 'index']);
            Route::post('/sms-templates', [SmsTemplateController::class, 'store']);
            Route::get('/sms-templates/{id}', [SmsTemplateController::class, 'show']);
            Route::put('/sms-templates/{id}', [SmsTemplateController::class, 'update']);
            Route::delete('/sms-templates/{id}', [SmsTemplateController::class, 'destroy']);
            Route::patch('/sms-templates/{id}/status', [SmsTemplateController::class, 'status']);
        });
    });
});
