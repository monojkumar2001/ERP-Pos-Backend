<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountingController;
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

    Route::prefix('accounting')->group(function () {
        Route::get('/dashboard', [AccountingController::class, 'dashboard']);
        Route::get('/accounts', [AccountingController::class, 'accounts']);
        Route::get('/journals', [AccountingController::class, 'journals']);
        Route::get('/expenses', [AccountingController::class, 'expenses']);
        Route::get('/incomes', [AccountingController::class, 'incomes']);
        Route::get('/transfers', [AccountingController::class, 'transfers']);
        Route::get('/recurring-journals', [AccountingController::class, 'recurringJournals']);
    });
});
