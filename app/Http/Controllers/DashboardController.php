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
use Illuminate\Validation\ValidationException;
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
            'activity' => [],
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
        [$rangeStart, $rangeEnd] = $this->chartRange($request);

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

        $changes = $this->periodChanges($messId, $now);

        return response()->json([
            'user' => $actor->load('mess:id,name,code,fund_type'),
            'mess_totals' => [
                'total_deposit' => round($messDeposits, 2),
                'total_expense' => round($messExpenses, 2),
                'manager_fund' => round($managerFund, 2),
                'mess_fund' => round($messFund, 2),
                'balance' => round($messDeposits - $messExpenses, 2),
                'deposit_change' => $changes['deposits'],
                'expense_change' => $changes['expenses'],
                'balance_change' => $changes['balance'],
            ],
            'chart' => $this->chart($messId, (int) $actor->id, $rangeStart, $rangeEnd, $mealColumns),
            'activity' => $this->recentActivity($messId),
            'my_totals' => [
                'total_deposit' => round($myDeposits, 2),
                'month_deposit' => round($monthDeposit, 2),
                'total_meals' => $this->mealCount($mine, $mealColumns),
                'month_meals' => $this->mealCount($monthRows, $mealColumns),
                'today' => $this->todayMeals($messId, $todayRow),
            ],
        ]);
    }

    private function chartRange(Request $request): array
    {
        $data = $request->validate([
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $today = Carbon::now('Asia/Kolkata')->startOfDay();
        $start = empty($data['from_date'])
            ? $today->copy()->startOfMonth()
            : Carbon::parse($data['from_date'], 'Asia/Kolkata')->startOfDay();
        $end = empty($data['to_date'])
            ? $today->copy()
            : Carbon::parse($data['to_date'], 'Asia/Kolkata')->startOfDay();

        if ($end->gt($today)) {
            $end = $today->copy();
        }

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'to_date' => ['The end date must be on or after the start date.'],
            ]);
        }

        if (abs($start->diffInDays($end)) > 92) {
            throw ValidationException::withMessages([
                'to_date' => ['Choose a range of 92 days or less.'],
            ]);
        }

        return [$start, $end];
    }

    private function periodChanges(int $messId, Carbon $now): array
    {
        $day = (int) $now->format('j');
        $thisStart = $now->copy()->startOfMonth();
        $thisEnd = $now->copy()->startOfDay();
        $prevStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $prevStart->copy()->addDays(min($day, $prevStart->daysInMonth) - 1);

        $thisDeposits = $this->depositSum($messId, $thisStart, $thisEnd);
        $prevDeposits = $this->depositSum($messId, $prevStart, $prevEnd);
        $thisExpenses = $this->expenseSum($messId, $thisStart, $thisEnd);
        $prevExpenses = $this->expenseSum($messId, $prevStart, $prevEnd);

        return [
            'deposits' => $this->percentChange($thisDeposits, $prevDeposits),
            'expenses' => $this->percentChange($thisExpenses, $prevExpenses),
            'balance' => $this->percentChange($thisDeposits - $thisExpenses, $prevDeposits - $prevExpenses),
        ];
    }

    private function depositSum(int $messId, Carbon $start, Carbon $end): float
    {
        return (float) DB::table('deposits')
            ->where('mess_id', $messId)
            ->whereRaw("STR_TO_DATE(deposited_on, '%d-%m-%Y') >= ?", [$start->toDateString()])
            ->whereRaw("STR_TO_DATE(deposited_on, '%d-%m-%Y') <= ?", [$end->toDateString()])
            ->sum('amount');
    }

    private function expenseSum(int $messId, Carbon $start, Carbon $end): float
    {
        return (float) DB::table('expense_entries')
            ->where('mess_id', $messId)
            ->whereDate('spent_on', '>=', $start->toDateString())
            ->whereDate('spent_on', '<=', $end->toDateString())
            ->sum('amount');
    }

    private function percentChange(float $current, float $previous): ?int
    {
        if (abs($previous) < 0.005) {
            return null;
        }

        return (int) round((($current - $previous) / abs($previous)) * 100);
    }

    private function recentActivity(int $messId): array
    {
        $rows = [];

        foreach (DepositModel::query()->where('mess_id', $messId)->orderByDesc('id')->limit(8)->get() as $row) {
            $rows[] = [
                'kind' => 'deposit',
                'date' => (string) $row->deposited_on,
                'sort' => $this->activitySort((string) $row->deposited_on, (int) $row->id),
                'title' => $row->note ?: $this->depositTitle((string) $row->deposit_type),
                'amount' => round((float) $row->amount, 2),
            ];
        }

        foreach (ExpenseEntryModel::query()->where('mess_id', $messId)->orderByDesc('id')->limit(8)->get() as $row) {
            $date = $row->spent_on?->format('d-m-Y') ?? '';
            $rows[] = [
                'kind' => 'expense',
                'date' => $date,
                'sort' => $this->activitySort($date, (int) $row->id),
                'title' => $row->title ?: ($row->note ?: ucfirst((string) $row->category)),
                'amount' => round((float) $row->amount, 2),
            ];
        }

        usort($rows, fn (array $left, array $right) => strcmp($right['sort'], $left['sort']));

        return array_map(function (array $row) {
            unset($row['sort']);

            return $row;
        }, array_slice($rows, 0, 6));
    }

    private function depositTitle(string $type): string
    {
        return match ($type) {
            'monthly' => 'Monthly mess deposit',
            'join' => 'Join deposit',
            'extra' => 'Extra deposit',
            default => 'Member deposit',
        };
    }

    private function activitySort(string $date, int $id): string
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $date, $match)) {
            return $match[3].$match[2].$match[1].str_pad((string) $id, 8, '0', STR_PAD_LEFT);
        }

        return '00000000'.str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    }

    private function chart(int $messId, int $userId, Carbon $start, Carbon $end, array $mealColumns): array
    {
        $points = [];
        $sameMonth = $start->format('Y-m') === $end->format('Y-m');

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $points[$cursor->toDateString()] = [
                'label' => $sameMonth ? $cursor->format('j') : $cursor->format('j M'),
                'date' => $cursor->format('j M Y'),
                'deposits' => 0.0,
                'mine' => 0.0,
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

        $mine = DB::table('deposits')
            ->where('mess_id', $messId)
            ->where('user_id', $userId)
            ->whereNotNull('deposited_on')
            ->selectRaw("DATE_FORMAT(STR_TO_DATE(deposited_on, '%d-%m-%Y'), '%Y-%m-%d') as day, SUM(amount) as total")
            ->groupBy('day')
            ->get();

        foreach ($mine as $row) {
            if (isset($points[$row->day])) {
                $points[$row->day]['mine'] = round((float) $row->total, 2);
            }
        }

        $expenses = DB::table('expense_entries')
            ->where('mess_id', $messId)
            ->whereDate('spent_on', '>=', $start->toDateString())
            ->whereDate('spent_on', '<=', $end->toDateString())
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
                ->whereDate('meal_date', '<=', $end->toDateString())
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
