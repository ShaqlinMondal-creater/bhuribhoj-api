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

/*
| The first segment is who may call the route.
| /public     no login
| /authorize  any signed-in role: me, profile, logout
| /admin      admin only
| /president  president only
| /manager    manager only
| /member     member only
*/

Route::prefix('public')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'authLogin']);
        Route::post('/register/email', [RegisterController::class, 'registerEmail']);
        Route::post('/register/preview', [RegisterController::class, 'registerPreview']);
        Route::post('/register/complete', [RegisterController::class, 'registerComplete']);
        Route::post('/google', [RegisterController::class, 'google']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('authorize')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/me', [AuthController::class, 'authMe']);
            Route::post('/profile', [AuthController::class, 'authProfile']);
            Route::post('/logout', [AuthController::class, 'authLogout']);
        });
    });

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::prefix('plans')->group(function () {
            Route::post('/fetch', [PlanController::class, 'plansFetch']);
            Route::post('/create', [PlanController::class, 'plansCreate']);
            Route::post('/update', [PlanController::class, 'plansUpdate']);
            Route::delete('/{id}/delete', [PlanController::class, 'plansDelete']);
        });

        Route::post('/messes/open', [MessController::class, 'messesOpen']);

        Route::middleware('mess.ready')->group(function () {
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
                Route::post('/fetch', [MemberController::class, 'membersFetch']);
                Route::post('/create', [MemberController::class, 'membersCreate']);
                Route::post('/update', [MemberController::class, 'membersUpdate']);
            });

            Route::post('/dashboard/fetch', [DashboardController::class, 'dashboardFetch']);

            Route::prefix('daily-meals')->group(function () {
                Route::post('/fetch', [DailyMealController::class, 'dailyMealsFetch']);
                Route::post('/save', [DailyMealController::class, 'dailyMealsSave']);
                Route::delete('/{id}/delete', [DailyMealController::class, 'dailyMealsDelete']);
            });

            Route::prefix('daily-menus')->group(function () {
                Route::post('/fetch', [DailyMenuController::class, 'dailyMenusFetch']);
                Route::post('/create', [DailyMenuController::class, 'dailyMenusCreate']);
                Route::delete('/{id}/delete', [DailyMenuController::class, 'dailyMenusDelete']);
            });

            Route::prefix('expenses')->group(function () {
                Route::post('/fetch', [ExpenseController::class, 'expensesFetch']);
                Route::post('/create', [ExpenseController::class, 'expensesCreate']);
                Route::delete('/{id}/delete', [ExpenseController::class, 'expensesDelete']);
            });
        });
    });

    Route::middleware('role:president')->prefix('president')->group(function () {
        Route::prefix('subscriptions')->group(function () {
            Route::post('/order', [SubscriptionController::class, 'subscriptionsOrder']);
            Route::post('/fail', [SubscriptionController::class, 'subscriptionsFail']);
            Route::post('/verify', [SubscriptionController::class, 'subscriptionsVerify']);
        });

        Route::post('/messes/open', [MessController::class, 'messesOpen']);

        Route::middleware('mess.ready')->group(function () {
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
                Route::post('/fetch', [MemberController::class, 'membersFetch']);
                Route::post('/create', [MemberController::class, 'membersCreate']);
                Route::post('/update', [MemberController::class, 'membersUpdate']);
            });

            Route::post('/dashboard/fetch', [DashboardController::class, 'dashboardFetch']);

            Route::prefix('daily-meals')->group(function () {
                Route::post('/fetch', [DailyMealController::class, 'dailyMealsFetch']);
                Route::post('/save', [DailyMealController::class, 'dailyMealsSave']);
                Route::delete('/{id}/delete', [DailyMealController::class, 'dailyMealsDelete']);
            });

            Route::prefix('daily-menus')->group(function () {
                Route::post('/fetch', [DailyMenuController::class, 'dailyMenusFetch']);
                Route::post('/create', [DailyMenuController::class, 'dailyMenusCreate']);
                Route::delete('/{id}/delete', [DailyMenuController::class, 'dailyMenusDelete']);
            });

            Route::prefix('expenses')->group(function () {
                Route::post('/fetch', [ExpenseController::class, 'expensesFetch']);
                Route::post('/create', [ExpenseController::class, 'expensesCreate']);
                Route::delete('/{id}/delete', [ExpenseController::class, 'expensesDelete']);
            });
        });
    });

    Route::middleware('role:manager')->prefix('manager')->group(function () {
        Route::middleware('mess.ready')->group(function () {
            Route::post('/members/fetch', [MemberController::class, 'membersFetch']);
            Route::post('/dashboard/fetch', [DashboardController::class, 'dashboardFetch']);

            Route::prefix('daily-meals')->group(function () {
                Route::post('/fetch', [DailyMealController::class, 'dailyMealsFetch']);
                Route::post('/save', [DailyMealController::class, 'dailyMealsSave']);
                Route::delete('/{id}/delete', [DailyMealController::class, 'dailyMealsDelete']);
            });

            Route::prefix('daily-menus')->group(function () {
                Route::post('/fetch', [DailyMenuController::class, 'dailyMenusFetch']);
                Route::post('/create', [DailyMenuController::class, 'dailyMenusCreate']);
                Route::delete('/{id}/delete', [DailyMenuController::class, 'dailyMenusDelete']);
            });

            Route::prefix('expenses')->group(function () {
                Route::post('/fetch', [ExpenseController::class, 'expensesFetch']);
                Route::post('/create', [ExpenseController::class, 'expensesCreate']);
                Route::delete('/{id}/delete', [ExpenseController::class, 'expensesDelete']);
            });
        });
    });

    Route::middleware('role:member')->prefix('member')->group(function () {
        Route::middleware('mess.ready')->group(function () {
            Route::post('/dashboard/fetch', [DashboardController::class, 'dashboardFetch']);

            Route::prefix('daily-meals')->group(function () {
                Route::post('/fetch', [DailyMealController::class, 'dailyMealsFetch']);
                Route::post('/save', [DailyMealController::class, 'dailyMealsSave']);
            });

            Route::post('/daily-menus/fetch', [DailyMenuController::class, 'dailyMenusFetch']);
            Route::post('/expenses/fetch', [ExpenseController::class, 'expensesFetch']);
        });
    });
});
