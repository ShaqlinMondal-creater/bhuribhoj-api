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

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'menu_date' => ['sometimes', 'nullable', 'date'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
            'meal_type' => ['sometimes', 'nullable', Rule::in(['lunch', 'dinner'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $query = DailyMenuModel::query()
            ->with(['decider:id,name'])
            ->where('mess_id', $messId);

        if (! empty($data['menu_date'])) {
            $query->whereDate('menu_date', $data['menu_date']);
        }

        if (! empty($data['from_date'])) {
            $query->whereDate('menu_date', '>=', $data['from_date']);
        }

        if (! empty($data['to_date'])) {
            $query->whereDate('menu_date', '<=', $data['to_date']);
        }

        if (! empty($data['meal_type'])) {
            $query->where('meal_type', $data['meal_type']);
        }

        $search = trim((string) ($data['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where('dishes', 'like', $like);
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
