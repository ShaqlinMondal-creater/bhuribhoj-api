<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DailyMealModel;
use App\Models\MealAwayModel;
use App\Models\MessMealModel;
use App\Models\UserModel;
use App\Support\MealAttendance;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DailyMealController extends Controller
{
    use ResolvesMessId;

    public function dailyMealsFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, [
            'meal_date' => now()->toDateString(),
            'summary' => [
                'breakfast_active' => 0,
                'breakfast_taken' => 0,
                'lunch_active' => 0,
                'lunch_taken' => 0,
                'dinner_active' => 0,
                'dinner_taken' => 0,
            ],
            'data' => [],
        ])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'meal_date' => ['sometimes', 'date'],
            'user_id' => ['sometimes', 'integer'],
        ]);

        $mealDate = Carbon::parse($data['meal_date'] ?? now())->timezone('Asia/Kolkata')->toDateString();
        MealAttendance::sync($messId, $mealDate);

        $aways = MealAwayModel::query()
            ->where('mess_id', $messId)
            ->whereDate('from_date', '<=', $mealDate)
            ->where(function ($query) use ($mealDate) {
                $query->whereNull('to_date')->orWhereDate('to_date', '>=', $mealDate);
            })
            ->get()
            ->groupBy('user_id');

        $query = DailyMealModel::query()
            ->with(['user:id,name,member_code,room_no,role'])
            ->where('mess_id', $messId)
            ->whereDate('meal_date', $mealDate);

        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        $active = MessMealModel::activeTypes($messId);

        $rows = $query->orderBy('user_id')->get()->map(function (DailyMealModel $row) use ($aways, $mealDate, $active) {
            $mine = $aways->get($row->user_id, collect());
            $away = false;

            foreach ($active as $meal) {
                if (MealAttendance::covers($mine, $mealDate, $meal)) {
                    $away = true;
                    break;
                }
            }

            $row->setAttribute('away', $away);

            return $row;
        });

        return response()->json([
            'meal_date' => $mealDate,
            'meals' => $active,
            'summary' => [
                'breakfast_active' => $rows->where('breakfast_active', true)->count(),
                'breakfast_taken' => $rows->where('breakfast_taken', true)->count(),
                'lunch_active' => $rows->where('lunch_active', true)->count(),
                'lunch_taken' => $rows->where('lunch_taken', true)->count(),
                'dinner_active' => $rows->where('dinner_active', true)->count(),
                'dinner_taken' => $rows->where('dinner_taken', true)->count(),
            ],
            'data' => $rows,
        ]);
    }

    public function dailyMealsSave(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'meal_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'items.*.breakfast_active' => ['sometimes', 'boolean'],
            'items.*.breakfast_taken' => ['sometimes', 'boolean'],
            'items.*.lunch_active' => ['sometimes', 'boolean'],
            'items.*.lunch_taken' => ['sometimes', 'boolean'],
            'items.*.dinner_active' => ['sometimes', 'boolean'],
            'items.*.dinner_taken' => ['sometimes', 'boolean'],
        ]);

        $saved = DB::transaction(function () use ($data, $messId, $actor) {
            $out = [];

            foreach ($data['items'] as $item) {
                $member = UserModel::query()
                    ->where('id', $item['user_id'])
                    ->where('mess_id', $messId)
                    ->first();

                if (! $member) {
                    throw ValidationException::withMessages([
                        'items' => ["User {$item['user_id']} does not belong to this mess."],
                    ]);
                }

                if ($actor->role === 'member' && (int) $actor->id !== (int) $member->id) {
                    abort(403, 'Members can only update their own meal.');
                }

                $existing = DailyMealModel::query()
                    ->where('mess_id', $messId)
                    ->where('user_id', $member->id)
                    ->whereDate('meal_date', $data['meal_date'])
                    ->first();

                $row = DailyMealModel::query()->updateOrCreate(
                    [
                        'mess_id' => $messId,
                        'user_id' => $member->id,
                        'meal_date' => $data['meal_date'],
                    ],
                    [
                        'breakfast_active' => array_key_exists('breakfast_active', $item) ? $item['breakfast_active'] : ($existing->breakfast_active ?? false),
                        'breakfast_taken' => array_key_exists('breakfast_taken', $item) ? $item['breakfast_taken'] : ($existing->breakfast_taken ?? false),
                        'lunch_active' => array_key_exists('lunch_active', $item) ? $item['lunch_active'] : ($existing->lunch_active ?? false),
                        'lunch_taken' => array_key_exists('lunch_taken', $item) ? $item['lunch_taken'] : ($existing->lunch_taken ?? false),
                        'dinner_active' => array_key_exists('dinner_active', $item) ? $item['dinner_active'] : ($existing->dinner_active ?? false),
                        'dinner_taken' => array_key_exists('dinner_taken', $item) ? $item['dinner_taken'] : ($existing->dinner_taken ?? false),
                        'marked_by' => $actor->id,
                    ]
                );

                $out[] = $row->load('user:id,name,member_code,room_no,role');
            }

            return $out;
        });

        return response()->json([
            'message' => 'Daily meals saved.',
            'data' => $saved,
        ]);
    }

    public function dailyMealsDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $row = DailyMealModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail();

        $row->delete();

        return response()->json([
            'message' => 'Daily meal deleted.',
        ]);
    }
}
