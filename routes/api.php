<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AclRoleController;
use App\Http\Controllers\AclUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\BinLocationController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerTypeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\SupplierTypeController;
use App\Http\Controllers\WarehouseController;

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
            Route::get('/journals', [AccountingController::class, 'journals']);
            Route::get('/expenses', [AccountingController::class, 'expenses']);
            Route::get('/incomes', [AccountingController::class, 'incomes']);
            Route::get('/transfers', [AccountingController::class, 'transfers']);
            Route::get('/recurring-journals', [AccountingController::class, 'recurringJournals']);
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

        Route::prefix('item-config')->group(function () {
            Route::get('/manufacturers', [CatalogController::class, 'manufacturers']);
            Route::post('/manufacturers', [CatalogController::class, 'storeManufacturer']);
            Route::put('/manufacturers/{id}', [CatalogController::class, 'updateManufacturer']);
            Route::get('/brands', [CatalogController::class, 'brands']);
            Route::post('/brands', [CatalogController::class, 'storeBrand']);
            Route::put('/brands/{id}', [CatalogController::class, 'updateBrand']);
            Route::get('/colors', [CatalogController::class, 'colors']);
            Route::post('/colors', [CatalogController::class, 'storeColor']);
            Route::put('/colors/{id}', [CatalogController::class, 'updateColor']);
            Route::get('/size-types', [CatalogController::class, 'sizeTypes']);
            Route::post('/size-types', [CatalogController::class, 'storeSizeType']);
            Route::get('/size-types/{id}', [CatalogController::class, 'showSizeType']);
            Route::put('/size-types/{id}', [CatalogController::class, 'updateSizeType']);
            Route::get('/attribute-sets', [CatalogController::class, 'attributeSets']);
            Route::post('/attribute-sets', [CatalogController::class, 'storeAttributeSet']);
            Route::get('/attribute-sets/{id}', [CatalogController::class, 'showAttributeSet']);
            Route::put('/attribute-sets/{id}', [CatalogController::class, 'updateAttributeSet']);
            Route::get('/categories', [CatalogController::class, 'categories']);
            Route::post('/categories', [CatalogController::class, 'storeCategory']);
            Route::put('/categories/{id}', [CatalogController::class, 'updateCategory']);
            Route::get('/uom-conversions', [CatalogController::class, 'uomConversions']);
            Route::post('/uom-conversions', [CatalogController::class, 'storeUomConversion']);
            Route::put('/uom-conversions/{id}', [CatalogController::class, 'updateUomConversion']);
            Route::get('/price-lists', [CatalogController::class, 'priceLists']);
            Route::post('/price-lists', [CatalogController::class, 'storePriceList']);
            Route::get('/price-lists/{id}', [CatalogController::class, 'showPriceList']);
            Route::put('/price-lists/{id}', [CatalogController::class, 'updatePriceList']);

            Route::get('/items', [ItemController::class, 'index']);
            Route::post('/items', [ItemController::class, 'store']);
            Route::get('/items/{id}', [ItemController::class, 'show']);
            Route::put('/items/{id}', [ItemController::class, 'update']);
            Route::delete('/items/{id}', [ItemController::class, 'destroy']);
            Route::patch('/items/{id}/status', [ItemController::class, 'status']);
            Route::patch('/items/{id}/opening-stock', [ItemController::class, 'openingStock']);

            Route::get('/{resource}/{id}', [CatalogController::class, 'show']);
            Route::patch('/{resource}/{id}/status', [CatalogController::class, 'status']);
            Route::delete('/{resource}/{id}', [CatalogController::class, 'destroy']);
        });

        Route::prefix('sales')->group(function () {
            Route::apiResource('customer-types', CustomerTypeController::class)->except('show');
            Route::patch('/customer-types/{id}/status', [CustomerTypeController::class, 'status']);
            Route::apiResource('customers', CustomerController::class);
            Route::patch('/customers/{id}/status', [CustomerController::class, 'status']);
        });

        Route::prefix('purchase')->group(function () {
            Route::apiResource('supplier-types', SupplierTypeController::class)->except('show');
            Route::patch('/supplier-types/{id}/status', [SupplierTypeController::class, 'status']);
            Route::apiResource('suppliers', SupplierController::class);
            Route::patch('/suppliers/{id}/status', [SupplierController::class, 'status']);
        });

        Route::prefix('inventory')->group(function () {
            Route::apiResource('warehouses', WarehouseController::class);
            Route::patch('/warehouses/{id}/status', [WarehouseController::class, 'status']);
            Route::apiResource('bin-locations', BinLocationController::class);
            Route::patch('/bin-locations/{id}/status', [BinLocationController::class, 'status']);
        });

        Route::prefix('pos')->group(function () {
            Route::apiResource('outlets', OutletController::class);
            Route::patch('/outlets/{id}/status', [OutletController::class, 'status']);
        });
    });
});
