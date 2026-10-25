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
            'summary' => $this->mealSummary(collect()),
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
            'summary' => $this->mealSummary($rows),
            'data' => $rows,
        ]);
    }

    public function dailyMealsSave(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $rules = [
            'meal_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];

        foreach (MessMealModel::TYPES as $meal) {
            $rules['items.*.'.$meal.'_active'] = ['sometimes', 'boolean'];
            $rules['items.*.'.$meal.'_taken'] = ['sometimes', 'boolean'];
        }

        $data = $request->validate($rules);

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
                    $this->mealFlags($item, $existing, (int) $actor->id)
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

    private function mealSummary($rows): array
    {
        $summary = [];

        foreach (MessMealModel::TYPES as $meal) {
            $summary[$meal.'_active'] = $rows->where($meal.'_active', true)->count();
            $summary[$meal.'_taken'] = $rows->where($meal.'_taken', true)->count();
        }

        return $summary;
    }

    private function mealFlags(array $item, ?DailyMealModel $existing, int $actorId): array
    {
        $flags = ['marked_by' => $actorId];

        foreach (MessMealModel::TYPES as $meal) {
            foreach (['active', 'taken'] as $kind) {
                $key = $meal.'_'.$kind;
                $flags[$key] = array_key_exists($key, $item) ? $item[$key] : ($existing?->{$key} ?? false);
            }
        }

        return $flags;
    }
}
