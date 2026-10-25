<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MessMealModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessMealController extends Controller
{
    use ResolvesMessId;

    public function messMealsFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);
        MessMealModel::ensure($messId);

        $rows = MessMealModel::query()
            ->where('mess_id', $messId)
            ->get()
            ->sortBy(fn (MessMealModel $row) => array_search($row->meals_type, MessMealModel::TYPES, true))
            ->values();

        return response()->json(['data' => $rows]);
    }

    public function messMealsSave(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'meals_type' => ['required', Rule::in(MessMealModel::TYPES)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        MessMealModel::ensure($messId);

        $row = MessMealModel::query()
            ->where('mess_id', $messId)
            ->where('meals_type', $data['meals_type'])
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'meals_type' => ['That meal was not found for this mess.'],
            ]);
        }

        if ($data['status'] === 'inactive' && $row->status === 'active') {
            $active = MessMealModel::query()
                ->where('mess_id', $messId)
                ->where('status', 'active')
                ->count();

            if ($active <= 1) {
                throw ValidationException::withMessages([
                    'status' => ['Keep at least one meal on.'],
                ]);
            }
        }

        $row->fill([
            'status' => $data['status'],
            'added_by' => $actor->id,
        ])->save();

        return response()->json([
            'message' => 'Meals updated.',
            'data' => $row,
        ]);
    }
}
