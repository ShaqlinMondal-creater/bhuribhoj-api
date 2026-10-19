<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DepositModel;
use App\Models\ExpenseEntryModel;
use App\Models\FineModel;
use App\Models\GuestMealModel;
use App\Models\MessModel;
use App\Models\MonthlyDueModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResolvesMessId;

    public function dashboardFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor->role === 'admin' && ! $request->filled('mess_id')) {
            return response()->json([
                'user' => $actor,
                'platform' => [
                    'mess_count' => MessModel::query()->count(),
                    'user_count' => UserModel::query()->count(),
                    'active_mess_count' => MessModel::query()->where('is_active', true)->count(),
                ],
                'mess_totals' => null,
                'my_totals' => null,
            ]);
        }

        $messId = $this->resolveMessId($actor, $request);

        $messDeposits = (float) DepositModel::query()->where('mess_id', $messId)->sum('amount');
        $messExpenses = (float) ExpenseEntryModel::query()->where('mess_id', $messId)->sum('amount');
        $managerFund = (float) DepositModel::query()->where('mess_id', $messId)->where('fund_type', 'manager_fund')->sum('amount');
        $messFund = (float) DepositModel::query()->where('mess_id', $messId)->where('fund_type', 'mess_fund')->sum('amount');

        $myDeposits = (float) DepositModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $actor->id)
            ->sum('amount');

        $myPendingDues = MonthlyDueModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $actor->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->get(['id', 'month', 'amount', 'late_fee', 'due_on', 'status']);

        $myGuestMeals = (float) GuestMealModel::query()
            ->where('mess_id', $messId)
            ->where('member_id', $actor->id)
            ->sum('total_amount');

        $myFines = (float) FineModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $actor->id)
            ->where('status', 'pending')
            ->sum('amount');

        return response()->json([
            'user' => $actor->load('mess:id,name,code,fund_mode'),
            'mess_totals' => [
                'total_deposit' => round($messDeposits, 2),
                'total_expense' => round($messExpenses, 2),
                'manager_fund' => round($managerFund, 2),
                'mess_fund' => round($messFund, 2),
                'balance' => round($messDeposits - $messExpenses, 2),
            ],
            'my_totals' => [
                'total_deposit' => round($myDeposits, 2),
                'guest_meals_amount' => round($myGuestMeals, 2),
                'pending_fines' => round($myFines, 2),
                'pending_dues' => $myPendingDues,
            ],
        ]);
    }
}
