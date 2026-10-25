<?php

namespace App\Support;

use App\Models\DailyMealModel;
use App\Models\MealAwayModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MealAttendance
{
    public static function sync(int $messId, string $date): void
    {
        $date = Carbon::parse($date, 'Asia/Kolkata')->toDateString();

        $members = UserModel::query()
            ->where('mess_id', $messId)
            ->where('status', 'active')
            ->whereIn('role', ['president', 'manager', 'member'])
            ->pluck('id');

        $aways = MealAwayModel::query()
            ->where('mess_id', $messId)
            ->whereDate('from_date', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('to_date')->orWhereDate('to_date', '>=', $date);
            })
            ->get()
            ->groupBy('user_id');

        foreach ($members as $userId) {
            $mine = $aways->get($userId, collect());
            $row = DailyMealModel::query()->firstOrNew([
                'mess_id' => $messId,
                'user_id' => $userId,
                'meal_date' => $date,
            ]);

            if (! $row->exists || ! self::locked($date, 'lunch')) {
                $row->lunch_active = ! self::covers($mine, $date, 'lunch');
            }

            if (! $row->exists || ! self::locked($date, 'dinner')) {
                $row->dinner_active = ! self::covers($mine, $date, 'dinner');
            }

            if (! $row->exists) {
                $row->lunch_taken = false;
                $row->dinner_taken = false;
            }

            $row->save();
        }
    }

    public static function syncSpan(int $messId, string $from, ?string $to): void
    {
        $start = Carbon::parse($from, 'Asia/Kolkata')->startOfDay();
        $today = self::now()->startOfDay();
        $end = $to ? Carbon::parse($to, 'Asia/Kolkata')->startOfDay() : $today->copy();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        if ($end->gt($start->copy()->addDays(120))) {
            $end = $start->copy()->addDays(120);
        }

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            self::sync($messId, $day->toDateString());
        }
    }

    public static function assertUnlocked(Collection $before, Collection $after): void
    {
        $start = self::now()->startOfDay();
        $today = $start->copy();

        foreach ($before->concat($after) as $row) {
            $from = Carbon::parse($row->from_date ?? $row['from_date'])->timezone('Asia/Kolkata')->startOfDay();
            if ($from->lt($start)) {
                $start = $from;
            }
        }

        if ($start->gt($today)) {
            return;
        }

        for ($day = $start->copy(); $day->lte($today); $day->addDay()) {
            $date = $day->toDateString();

            foreach (['lunch', 'dinner'] as $meal) {
                if (! self::locked($date, $meal)) {
                    continue;
                }

                if (self::covers($before, $date, $meal) !== self::covers($after, $date, $meal)) {
                    throw ValidationException::withMessages([
                        'from_date' => [self::lockedMessage($date, $meal)],
                    ]);
                }
            }
        }
    }

    public static function locked(string $date, string $meal): bool
    {
        $cutoff = $meal === 'dinner' ? '17:50' : '05:00';
        $moment = Carbon::parse($date.' '.$cutoff, 'Asia/Kolkata');

        return self::now()->greaterThanOrEqualTo($moment);
    }

    public static function covers(Collection $aways, string $date, string $meal): bool
    {
        $point = self::point($date, $meal);

        foreach ($aways as $away) {
            $start = self::point(self::day($away->from_date ?? $away['from_date']), $away->from_meal ?? $away['from_meal']);

            if ($point < $start) {
                continue;
            }

            $toDate = $away->to_date ?? ($away['to_date'] ?? null);
            $toMeal = $away->to_meal ?? ($away['to_meal'] ?? null);

            if ($toDate === null || $toMeal === null) {
                return true;
            }

            if ($point < self::point(self::day($toDate), $toMeal)) {
                return true;
            }
        }

        return false;
    }

    public static function now(): Carbon
    {
        return now()->timezone('Asia/Kolkata');
    }

    private static function day(mixed $value): string
    {
        return Carbon::parse($value)->timezone('Asia/Kolkata')->toDateString();
    }

    private static function point(string $date, string $meal): string
    {
        return $date.' '.($meal === 'dinner' ? '1' : '0');
    }

    private static function lockedMessage(string $date, string $meal): string
    {
        $label = $meal === 'dinner' ? 'Dinner' : 'Lunch';
        $when = Carbon::parse($date, 'Asia/Kolkata')->format('j M Y');

        return "{$label} on {$when} is already locked.";
    }
}
