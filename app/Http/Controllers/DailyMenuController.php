<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DailyMenuModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyMenuController extends Controller
{
    use ResolvesMessId;

    public function dailyMenusFetch(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'menu_date' => ['sometimes', 'date'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
        ]);

        $query = DailyMenuModel::query()
            ->with(['decider:id,name'])
            ->where('mess_id', $messId);

        if (! empty($data['menu_date'])) {
            $query->whereDate('menu_date', $data['menu_date']);
        } else {
            $from = $data['from_date'] ?? now()->startOfMonth()->toDateString();
            $to = $data['to_date'] ?? now()->endOfMonth()->toDateString();
            $query->whereBetween('menu_date', [$from, $to]);
        }

        return response()->json([
            'data' => $query->orderBy('menu_date')->orderBy('meal_type')->get(),
        ]);
    }

    public function dailyMenusCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'menu_date' => ['required', 'date'],
            'meal_type' => ['required', Rule::in(['lunch', 'dinner'])],
            'dishes' => ['required', 'string', 'max:2000'],
        ]);

        $menu = DailyMenuModel::query()->updateOrCreate(
            [
                'mess_id' => $messId,
                'menu_date' => $data['menu_date'],
                'meal_type' => $data['meal_type'],
            ],
            [
                'dishes' => $data['dishes'],
                'decided_by' => $actor->id,
            ]
        );

        return response()->json([
            'message' => 'Daily menu saved.',
            'data' => $menu->load('decider:id,name'),
        ], 201);
    }

    public function dailyMenusDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        DailyMenuModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Daily menu deleted.',
        ]);
    }
}
