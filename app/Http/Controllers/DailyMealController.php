<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DailyMealModel;
use App\Models\UserModel;
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
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'meal_date' => ['sometimes', 'date'],
            'user_id' => ['sometimes', 'integer'],
        ]);

        $mealDate = $data['meal_date'] ?? now()->toDateString();

        $query = DailyMealModel::query()
            ->with(['user:id,name,member_code,room_no,role'])
            ->where('mess_id', $messId)
            ->whereDate('meal_date', $mealDate);

        if ($actor->role === 'member') {
            $query->where('user_id', $actor->id);
        } elseif (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        $rows = $query->orderBy('user_id')->get();

        return response()->json([
            'meal_date' => $mealDate,
            'summary' => [
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

                $row = DailyMealModel::query()->updateOrCreate(
                    [
                        'mess_id' => $messId,
                        'user_id' => $member->id,
                        'meal_date' => $data['meal_date'],
                    ],
                    [
                        'lunch_active' => $item['lunch_active'] ?? false,
                        'lunch_taken' => $item['lunch_taken'] ?? false,
                        'dinner_active' => $item['dinner_active'] ?? false,
                        'dinner_taken' => $item['dinner_taken'] ?? false,
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
