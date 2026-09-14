<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\AclRoleController;
use App\Http\Controllers\AclUserController;
use App\Http\Controllers\Api\UserController;

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
    });
});
