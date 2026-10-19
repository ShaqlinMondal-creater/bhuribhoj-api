<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConstantExpenseController;
use App\Http\Controllers\DailyMealController;
use App\Http\Controllers\DailyMenuController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\GuestMealController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\MarketDutyController;
use App\Http\Controllers\MarketPurchaseController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MessController;
use App\Http\Controllers\MessInvitationController;
use App\Http\Controllers\MessRuleController;
use App\Http\Controllers\MonthlyDueController;
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'authMe']);
        Route::post('/profile', [AuthController::class, 'authProfile']);
        Route::post('/logout', [AuthController::class, 'authLogout']);
    });
});

Route::prefix('plans')->group(function () {
    Route::post('/fetch', [PlanController::class, 'plansFetch']);
});

Route::middleware('auth:sanctum')->prefix('subscriptions')->group(function () {
    Route::post('/order', [SubscriptionController::class, 'subscriptionsOrder']);
    Route::post('/fail', [SubscriptionController::class, 'subscriptionsFail']);
    Route::post('/verify', [SubscriptionController::class, 'subscriptionsVerify']);
});

Route::prefix('mess-invitations')->group(function () {
    Route::get('/token/{token}/fetch', [MessInvitationController::class, 'messInvitationsTokenFetch']);
    Route::post('/token/{token}/accept', [MessInvitationController::class, 'messInvitationsTokenAccept']);

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::post('/fetch', [MessInvitationController::class, 'messInvitationsFetch']);
        Route::post('/create', [MessInvitationController::class, 'messInvitationsCreate']);
        Route::delete('/{id}/cancel', [MessInvitationController::class, 'messInvitationsCancel']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/messes/open', [MessController::class, 'messesOpen']);

    Route::middleware('mess.ready')->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::post('/fetch', [DashboardController::class, 'dashboardFetch'])
            ->middleware('role:admin,president,manager,member');
    });

    Route::prefix('messes')->group(function () {
        Route::post('/fetch', [MessController::class, 'messesFetch'])
            ->middleware('role:admin,president,manager,member');
        Route::post('/update', [MessController::class, 'messesUpdate'])
            ->middleware('role:admin,president');
    });

    Route::prefix('members')->group(function () {
        Route::post('/fetch', [MemberController::class, 'membersFetch'])->middleware('role:admin,president,manager');
        Route::post('/create', [MemberController::class, 'membersCreate'])->middleware('role:admin,president');
        Route::post('/update', [MemberController::class, 'membersUpdate'])->middleware('role:admin,president');
    });

    Route::prefix('managers')->middleware('role:admin,president')->group(function () {
        Route::post('/fetch', [ManagerController::class, 'managersFetch']);
        Route::post('/assign', [ManagerController::class, 'managersAssign']);
        Route::delete('/{id}/remove', [ManagerController::class, 'managersRemove']);
    });

    Route::prefix('deposits')->group(function () {
        Route::post('/fetch', [DepositController::class, 'depositsFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [DepositController::class, 'depositsCreate'])->middleware('role:admin,president,manager,member');
    });

    Route::prefix('monthly-dues')->group(function () {
        Route::post('/fetch', [MonthlyDueController::class, 'monthlyDuesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/generate', [MonthlyDueController::class, 'monthlyDuesGenerate'])->middleware('role:admin,president,manager');
        Route::post('/pay', [MonthlyDueController::class, 'monthlyDuesPay'])->middleware('role:admin,president,manager,member');
        Route::post('/waive', [MonthlyDueController::class, 'monthlyDuesWaive'])->middleware('role:admin,president');
        Route::post('/mark-overdue', [MonthlyDueController::class, 'monthlyDuesMarkOverdue'])->middleware('role:admin,president,manager');
    });

    Route::prefix('daily-meals')->group(function () {
        Route::post('/fetch', [DailyMealController::class, 'dailyMealsFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/save', [DailyMealController::class, 'dailyMealsSave'])->middleware('role:admin,president,manager,member');
        Route::delete('/{id}/delete', [DailyMealController::class, 'dailyMealsDelete'])->middleware('role:admin,president,manager');
    });

    Route::prefix('daily-menus')->group(function () {
        Route::post('/fetch', [DailyMenuController::class, 'dailyMenusFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [DailyMenuController::class, 'dailyMenusCreate'])->middleware('role:admin,president,manager');
        Route::delete('/{id}/delete', [DailyMenuController::class, 'dailyMenusDelete'])->middleware('role:admin,president,manager');
    });

    Route::prefix('market-purchases')->group(function () {
        Route::post('/fetch', [MarketPurchaseController::class, 'marketPurchasesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [MarketPurchaseController::class, 'marketPurchasesCreate'])->middleware('role:admin,president,manager');
        Route::delete('/{id}/delete', [MarketPurchaseController::class, 'marketPurchasesDelete'])->middleware('role:admin,president,manager');
    });

    Route::prefix('market-duties')->group(function () {
        Route::post('/fetch', [MarketDutyController::class, 'marketDutiesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [MarketDutyController::class, 'marketDutiesCreate'])->middleware('role:admin,president,manager');
        Route::post('/status', [MarketDutyController::class, 'marketDutiesStatus'])->middleware('role:admin,president,manager,member');
        Route::delete('/{id}/delete', [MarketDutyController::class, 'marketDutiesDelete'])->middleware('role:admin,president,manager');
    });

    Route::prefix('guest-meals')->group(function () {
        Route::post('/fetch', [GuestMealController::class, 'guestMealsFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [GuestMealController::class, 'guestMealsCreate'])->middleware('role:admin,president,manager,member');
        Route::delete('/{id}/delete', [GuestMealController::class, 'guestMealsDelete'])->middleware('role:admin,president,manager,member');
    });

    Route::prefix('constant-expenses')->group(function () {
        Route::post('/fetch', [ConstantExpenseController::class, 'constantExpensesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [ConstantExpenseController::class, 'constantExpensesCreate'])->middleware('role:admin,president');
        Route::delete('/{id}/delete', [ConstantExpenseController::class, 'constantExpensesDelete'])->middleware('role:admin,president');
    });

    Route::prefix('expenses')->group(function () {
        Route::post('/fetch', [ExpenseController::class, 'expensesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [ExpenseController::class, 'expensesCreate'])->middleware('role:admin,president,manager');
        Route::delete('/{id}/delete', [ExpenseController::class, 'expensesDelete'])->middleware('role:admin,president,manager');
    });

    Route::prefix('mess-rules')->group(function () {
        Route::post('/fetch', [MessRuleController::class, 'messRulesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [MessRuleController::class, 'messRulesCreate'])->middleware('role:admin,president');
        Route::delete('/{id}/delete', [MessRuleController::class, 'messRulesDelete'])->middleware('role:admin,president');
    });

    Route::prefix('fines')->group(function () {
        Route::post('/fetch', [FineController::class, 'finesFetch'])->middleware('role:admin,president,manager,member');
        Route::post('/create', [FineController::class, 'finesCreate'])->middleware('role:admin,president,manager');
        Route::post('/pay', [FineController::class, 'finesPay'])->middleware('role:admin,president,manager,member');
        Route::post('/waive', [FineController::class, 'finesWaive'])->middleware('role:admin,president');
        Route::delete('/{id}/delete', [FineController::class, 'finesDelete'])->middleware('role:admin,president');
    });
    });
});
