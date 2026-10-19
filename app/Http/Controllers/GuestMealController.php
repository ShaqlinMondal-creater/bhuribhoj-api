<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\GuestMealModel;
use App\Models\MessModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GuestMealController extends Controller
{
    use ResolvesMessId;

    public function guestMealsFetch(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'meal_date' => ['sometimes', 'date'],
            'member_id' => ['sometimes', 'integer'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
        ]);

        $query = GuestMealModel::query()
            ->with(['member:id,name,member_code'])
            ->where('mess_id', $messId);

        if ($actor->role === 'member') {
            $query->where('member_id', $actor->id);
        } elseif (! empty($data['member_id'])) {
            $query->where('member_id', $data['member_id']);
        }

        if (! empty($data['meal_date'])) {
            $query->whereDate('meal_date', $data['meal_date']);
        } elseif (! empty($data['from_date']) || ! empty($data['to_date'])) {
            $query->whereBetween('meal_date', [
                $data['from_date'] ?? now()->startOfMonth()->toDateString(),
                $data['to_date'] ?? now()->toDateString(),
            ]);
        }

        $total = (float) (clone $query)->sum('total_amount');
        $rows = $query->latest('meal_date')->paginate(20);

        return response()->json([
            'total_amount' => round($total, 2),
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function guestMealsCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'member_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'guest_type' => ['required', Rule::in(['personal', 'guest'])],
            'dish_type' => ['required', Rule::in(['chicken', 'fish', 'egg', 'veg'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'meal_date' => ['required', 'date'],
            'meal_type' => ['nullable', Rule::in(['lunch', 'dinner'])],
            'guest_name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $memberId = $data['member_id'] ?? $actor->id;

        if ($actor->role === 'member') {
            $memberId = $actor->id;
        }

        $member = UserModel::query()
            ->where('id', $memberId)
            ->where('mess_id', $messId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'member_id' => ['Member does not belong to this mess.'],
            ]);
        }

        $unitPrice = match ($data['dish_type']) {
            'chicken' => (float) $mess->guest_chicken_price,
            'fish' => (float) $mess->guest_fish_price,
            'egg' => (float) $mess->guest_egg_price,
            'veg' => (float) $mess->guest_veg_price,
        };

        $guestMeal = GuestMealModel::query()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'guest_type' => $data['guest_type'],
            'dish_type' => $data['dish_type'],
            'quantity' => $data['quantity'],
            'unit_price' => $unitPrice,
            'total_amount' => $unitPrice * $data['quantity'],
            'meal_date' => $data['meal_date'],
            'meal_type' => $data['meal_type'] ?? null,
            'guest_name' => $data['guest_name'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'message' => 'Guest meal added.',
            'data' => $guestMeal->load('member:id,name,member_code'),
        ], 201);
    }

    public function guestMealsDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $row = GuestMealModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($actor->role === 'member' && (int) $actor->id !== (int) $row->member_id) {
            abort(403, 'Members can only delete their own guest meals.');
        }

        $row->delete();

        return response()->json([
            'message' => 'Guest meal deleted.',
        ]);
    }
}
