<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DailyMealModel;
use App\Models\DepositModel;
use App\Models\ExpenseEntryModel;
use App\Models\MessMealModel;
use App\Models\MessModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    use ResolvesMessId;

    public function dashboardFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, [
            'user' => $actor,
            'mess_totals' => [
                'total_deposit' => 0,
                'total_expense' => 0,
                'manager_fund' => 0,
                'mess_fund' => 0,
                'balance' => 0,
            ],
            'chart' => [],
            'my_totals' => [
                'total_deposit' => 0,
                'month_deposit' => 0,
                'total_meals' => 0,
                'month_meals' => 0,
                'today' => [],
            ],
        ])) {
            return $blank;
        }

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

        $now = Carbon::now('Asia/Kolkata');
        $monthLike = '%-'.$now->format('m-Y');
        $myDeposits = (float) DepositModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $actor->id)
            ->sum('amount');
        $monthDeposit = (float) DepositModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $actor->id)
            ->where('deposited_on', 'like', $monthLike)
            ->sum('amount');

        $today = $now->toDateString();
        $mealColumns = $this->mealColumns();
        $mine = DailyMealModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $actor->id)
            ->get($this->mealSelect($mealColumns));
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();
        $todayRow = $mine->first(fn (DailyMealModel $row) => $row->meal_date?->toDateString() === $today);
        $monthRows = $mine->filter(function (DailyMealModel $row) use ($monthStart, $monthEnd) {
            $date = $row->meal_date?->toDateString();

            return $date !== null && $date >= $monthStart && $date <= $monthEnd;
        });

        return response()->json([
            'user' => $actor->load('mess:id,name,code,fund_type'),
            'mess_totals' => [
                'total_deposit' => round($messDeposits, 2),
                'total_expense' => round($messExpenses, 2),
                'manager_fund' => round($managerFund, 2),
                'mess_fund' => round($messFund, 2),
                'balance' => round($messDeposits - $messExpenses, 2),
            ],
            'chart' => $this->chart($messId, $now, $mealColumns),
            'my_totals' => [
                'total_deposit' => round($myDeposits, 2),
                'month_deposit' => round($monthDeposit, 2),
                'total_meals' => $this->mealCount($mine, $mealColumns),
                'month_meals' => $this->mealCount($monthRows, $mealColumns),
                'today' => $this->todayMeals($messId, $todayRow),
            ],
        ]);
    }

    private function chart(int $messId, Carbon $now, array $mealColumns): array
    {
        $start = $now->copy()->startOfMonth();
        $points = [];

        for ($cursor = $start->copy(); $cursor->lte($now); $cursor->addDay()) {
            $points[$cursor->toDateString()] = [
                'label' => $cursor->format('j'),
                'deposits' => 0.0,
                'expenses' => 0.0,
                'balance' => 0.0,
                'meals' => 0,
            ];
        }

        $deposits = DB::table('deposits')
            ->where('mess_id', $messId)
            ->whereNotNull('deposited_on')
            ->selectRaw("DATE_FORMAT(STR_TO_DATE(deposited_on, '%d-%m-%Y'), '%Y-%m-%d') as day, SUM(amount) as total")
            ->groupBy('day')
            ->get();

        foreach ($deposits as $row) {
            if (isset($points[$row->day])) {
                $points[$row->day]['deposits'] = round((float) $row->total, 2);
            }
        }

        $expenses = DB::table('expense_entries')
            ->where('mess_id', $messId)
            ->whereDate('spent_on', '>=', $start->toDateString())
            ->whereDate('spent_on', '<=', $now->toDateString())
            ->selectRaw("DATE_FORMAT(spent_on, '%Y-%m-%d') as day, SUM(amount) as total")
            ->groupBy('day')
            ->get();

        foreach ($expenses as $row) {
            if (isset($points[$row->day])) {
                $points[$row->day]['expenses'] = round((float) $row->total, 2);
            }
        }

        $opening = (float) DB::table('deposits')
            ->where('mess_id', $messId)
            ->whereRaw("STR_TO_DATE(deposited_on, '%d-%m-%Y') < ?", [$start->toDateString()])
            ->sum('amount');
        $opening -= (float) DB::table('expense_entries')
            ->where('mess_id', $messId)
            ->whereDate('spent_on', '<', $start->toDateString())
            ->sum('amount');

        $running = $opening;

        foreach ($points as $day => $point) {
            $running += $point['deposits'] - $point['expenses'];
            $points[$day]['balance'] = round($running, 2);
        }

        if ($mealColumns !== [] && Schema::hasTable('daily_meals')) {
            $rows = DailyMealModel::query()
                ->where('mess_id', $messId)
                ->whereDate('meal_date', '>=', $start->toDateString())
                ->whereDate('meal_date', '<=', $now->toDateString())
                ->get($this->mealSelect($mealColumns));

            foreach ($rows as $row) {
                $day = $row->meal_date?->toDateString();
                if ($day && isset($points[$day])) {
                    $points[$day]['meals'] += $this->mealCount([$row], $mealColumns);
                }
            }
        }

        return array_values($points);
    }

    private function mealColumns(): array
    {
        return array_values(array_filter(
            ['breakfast_active', 'morning_snack_active', 'lunch_active', 'evening_snack_active', 'dinner_active', 'late_night_active'],
            fn (string $column) => Schema::hasColumn('daily_meals', $column)
        ));
    }

    private function mealSelect(array $columns): array
    {
        return array_merge(['id', 'meal_date'], $columns);
    }

    private function mealCount($rows, array $columns): int
    {
        $total = 0;

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                if ($row->{$column}) {
                    $total++;
                }
            }
        }

        return $total;
    }

    private function todayMeals(int $messId, ?DailyMealModel $row): array
    {
        $meals = [];

        foreach (MessMealModel::activeTypes($messId) as $type) {
            $column = $type.'_active';
            $meals[] = [
                'meal' => $type,
                'label' => MessMealModel::LABELS[$type] ?? ucfirst($type),
                'on' => $row ? (bool) ($row->{$column} ?? false) : true,
            ];
        }

        return $meals;
    }
}
