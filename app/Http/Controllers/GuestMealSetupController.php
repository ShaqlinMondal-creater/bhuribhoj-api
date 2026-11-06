<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\ActivityLogModel;
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

        ActivityLogModel::record(
            'guest_meal.create',
            'Guest meal '.$row->meal_name.' created.',
            $actor->id,
            'guest_meal_setup',
            $row->id,
            $messId,
            ['price' => (float) $row->price],
            $request->ip(),
        );

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
        $fresh = $row->fresh();

        ActivityLogModel::record(
            'guest_meal.update',
            'Guest meal '.$fresh->meal_name.' updated.',
            $actor->id,
            'guest_meal_setup',
            $fresh->id,
            $messId,
            array_keys($changes),
            $request->ip(),
        );

        return response()->json([
            'message' => 'Guest meal setup updated.',
            'data' => $fresh,
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

        $name = $row->meal_name;
        $row->delete();

        ActivityLogModel::record(
            'guest_meal.delete',
            'Guest meal '.$name.' deleted.',
            $actor->id,
            'guest_meal_setup',
            $id,
            $messId,
            [],
            $request->ip(),
        );

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
