<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\GuestMealSetupModel;
use App\Models\MessModel;
use App\Rules\KolkataDateRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GuestMealSetupController extends Controller
{
    use ResolvesMessId;

    public function guestMealSetupFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $rows = GuestMealSetupModel::query()
            ->where('mess_id', $messId)
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function guestMealSetupCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'meal_name' => ['required', 'string', 'max:120'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $row = GuestMealSetupModel::query()->create([
            'mess_id' => $messId,
            'meal_name' => $data['meal_name'],
            'price' => $data['price'],
        ]);

        MessModel::query()
            ->where('id', $messId)
            ->whereNull('guest_meal_setup_id')
            ->update(['guest_meal_setup_id' => $row->id]);

        return response()->json([
            'message' => 'Guest meal setup created.',
            'data' => $row,
        ], 201);
    }

    public function guestMealSetupUpdate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'id' => ['required', 'integer'],
            'meal_name' => ['sometimes', 'string', 'max:120'],
            'price' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $row = $this->setupForMess($messId, (int) $data['id']);

        $changes = array_intersect_key($data, array_flip(['meal_name', 'price']));

        if ($changes === []) {
            throw ValidationException::withMessages([
                'meal_name' => ['No fields to update.'],
            ]);
        }

        $row->fill($changes)->save();

        return response()->json([
            'message' => 'Guest meal setup updated.',
            'data' => $row->fresh(),
        ]);
    }

    public function guestMealSetupDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $row = $this->setupForMess($messId, $id);
        $mess = MessModel::query()->findOrFail($messId);

        if ((int) $mess->guest_meal_setup_id === (int) $row->id) {
            $nextId = GuestMealSetupModel::query()
                ->where('mess_id', $messId)
                ->where('id', '!=', $row->id)
                ->orderBy('id')
                ->value('id');

            $mess->guest_meal_setup_id = $nextId;
            $mess->save();
        }

        $row->delete();

        return response()->json([
            'message' => 'Guest meal setup deleted.',
        ]);
    }

    private function setupForMess(int $messId, int $id): GuestMealSetupModel
    {
        $row = GuestMealSetupModel::query()
            ->where('mess_id', $messId)
            ->where('id', $id)
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'id' => ['Guest meal setup was not found for this mess.'],
            ]);
        }

        return $row;
    }
}
