<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MarketDutyModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MarketDutyController extends Controller
{
    use ResolvesMessId;

    public function marketDutiesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(['assigned', 'done', 'missed', 'swapped'])],
        ]);

        $query = MarketDutyModel::query()
            ->with(['user:id,name,member_code,room_no', 'assigner:id,name'])
            ->where('mess_id', $messId)
            ->whereBetween('duty_date', [
                $data['from_date'] ?? now()->startOfMonth()->toDateString(),
                $data['to_date'] ?? now()->endOfMonth()->toDateString(),
            ]);

        if ($actor->role === 'member') {
            $query->where('user_id', $actor->id);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return response()->json([
            'data' => $query->orderBy('duty_date')->get(),
        ]);
    }

    public function marketDutiesCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'duty_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $member = UserModel::query()
            ->where('id', $data['user_id'])
            ->where('mess_id', $messId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['User does not belong to this mess.'],
            ]);
        }

        $duty = MarketDutyModel::query()->updateOrCreate(
            [
                'mess_id' => $messId,
                'user_id' => $member->id,
                'duty_date' => $data['duty_date'],
            ],
            [
                'status' => 'assigned',
                'assigned_by' => $actor->id,
                'note' => $data['note'] ?? null,
            ]
        );

        return response()->json([
            'message' => 'Market duty assigned.',
            'data' => $duty->load(['user:id,name,member_code', 'assigner:id,name']),
        ], 201);
    }

    public function marketDutiesStatus(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'duty_id' => ['required', 'integer', Rule::exists('market_duties', 'id')],
            'status' => ['required', Rule::in(['assigned', 'done', 'missed', 'swapped'])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $duty = MarketDutyModel::query()
            ->where('id', $data['duty_id'])
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($actor->role === 'member' && (int) $actor->id !== (int) $duty->user_id) {
            abort(403, 'Members can only update their own duty.');
        }

        $duty->forceFill([
            'status' => $data['status'],
            'note' => $data['note'] ?? $duty->note,
        ])->save();

        return response()->json([
            'message' => 'Market duty status updated.',
            'data' => $duty->fresh()->load(['user:id,name,member_code', 'assigner:id,name']),
        ]);
    }

    public function marketDutiesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        MarketDutyModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Market duty deleted.',
        ]);
    }
}
