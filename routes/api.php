<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyMealController;
use App\Http\Controllers\DailyMenuController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GuestMealSetupController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MessController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'authLogin']);
    Route::post('/register/email', [RegisterController::class, 'registerEmail']);
    Route::post('/register/preview', [RegisterController::class, 'registerPreview']);
    Route::post('/register/complete', [RegisterController::class, 'registerComplete']);
    Route::post('/google', [RegisterController::class, 'google']);
});

Route::prefix('plans')->group(function () {
    Route::post('/fetch', [PlanController::class, 'plansFetch']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'authMe']);
        Route::post('/profile', [AuthController::class, 'authProfile']);
        Route::post('/logout', [AuthController::class, 'authLogout']);
    });

    Route::prefix('subscriptions')->group(function () {
        Route::post('/order', [SubscriptionController::class, 'subscriptionsOrder']);
        Route::post('/fail', [SubscriptionController::class, 'subscriptionsFail']);
        Route::post('/verify', [SubscriptionController::class, 'subscriptionsVerify']);
    });

    Route::post('/messes/open', [MessController::class, 'messesOpen'])
        ->middleware('role:admin,president');

    Route::middleware('mess.ready')->group(function () {
        Route::middleware('role:admin,president')->group(function () {
            
            Route::prefix('messes')->group(function () {
                Route::post('/fetch', [MessController::class, 'messesFetch']);
                Route::post('/update', [MessController::class, 'messesUpdate']);
                Route::post('/assign', [MessController::class, 'messesAssign']);
                Route::delete('/{id}/delete', [MessController::class, 'messesDelete']);
            });

            Route::prefix('guest-meal-setup')->group(function () {
                Route::post('/fetch', [GuestMealSetupController::class, 'guestMealSetupFetch']);
                Route::post('/create', [GuestMealSetupController::class, 'guestMealSetupCreate']);
                Route::post('/update', [GuestMealSetupController::class, 'guestMealSetupUpdate']);
                Route::delete('/{id}/delete', [GuestMealSetupController::class, 'guestMealSetupDelete']);
            });

            Route::prefix('deposits')->group(function () {
                Route::post('/fetch', [DepositController::class, 'depositsFetch']);
                Route::post('/create', [DepositController::class, 'depositsCreate']);
                Route::post('/update', [DepositController::class, 'depositsUpdate']);
                Route::delete('/{id}/delete', [DepositController::class, 'depositsDelete']);
            });

            Route::prefix('members')->group(function () {
                Route::post('/create', [MemberController::class, 'membersCreate']);
                Route::post('/update', [MemberController::class, 'membersUpdate']);
            });
        });

        Route::middleware('role:admin,president,manager')->group(function () {
            Route::prefix('members')->group(function () {
                Route::post('/fetch', [MemberController::class, 'membersFetch']);
            });

            Route::prefix('daily-meals')->group(function () {
                Route::delete('/{id}/delete', [DailyMealController::class, 'dailyMealsDelete']);
            });

            Route::prefix('daily-menus')->group(function () {
                Route::post('/create', [DailyMenuController::class, 'dailyMenusCreate']);
                Route::delete('/{id}/delete', [DailyMenuController::class, 'dailyMenusDelete']);
            });

            Route::prefix('expenses')->group(function () {
                Route::post('/create', [ExpenseController::class, 'expensesCreate']);
                Route::delete('/{id}/delete', [ExpenseController::class, 'expensesDelete']);
            });

        });

        Route::middleware('role:admin,president,manager,member')->group(function () {
            Route::prefix('dashboard')->group(function () {
                Route::post('/fetch', [DashboardController::class, 'dashboardFetch']);
            });

            Route::prefix('daily-meals')->group(function () {
                Route::post('/fetch', [DailyMealController::class, 'dailyMealsFetch']);
                Route::post('/save', [DailyMealController::class, 'dailyMealsSave']);
            });

            Route::prefix('daily-menus')->group(function () {
                Route::post('/fetch', [DailyMenuController::class, 'dailyMenusFetch']);
            });

            Route::prefix('expenses')->group(function () {
                Route::post('/fetch', [ExpenseController::class, 'expensesFetch']);
            });

        });
    });
});
