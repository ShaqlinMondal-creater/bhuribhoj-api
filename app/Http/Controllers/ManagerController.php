<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MessManagerModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManagerController extends Controller
{
    use ResolvesMessId;

    public function managersFetch(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'month' => ['sometimes', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $month = $data['month'] ?? now()->format('Y-m');

        $managers = MessManagerModel::query()
            ->with(['user:id,name,email,mobile,room_no,member_code,role,status'])
            ->where('mess_id', $messId)
            ->where('month', $month)
            ->orderBy('id')
            ->get();

        return response()->json([
            'month' => $month,
            'max' => 2,
            'count' => $managers->count(),
            'data' => $managers,
        ]);
    }

    public function managersAssign(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
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

        if ($member->status !== 'active') {
            throw ValidationException::withMessages([
                'user_id' => ['Only active users can be assigned as managers.'],
            ]);
        }

        if ($member->role === 'president') {
            throw ValidationException::withMessages([
                'user_id' => ['President cannot be assigned as a month manager.'],
            ]);
        }

        $already = MessManagerModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $member->id)
            ->where('month', $data['month'])
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'user_id' => ['This user is already a manager for that month.'],
            ]);
        }

        $count = MessManagerModel::query()
            ->where('mess_id', $messId)
            ->where('month', $data['month'])
            ->count();

        if ($count >= 2) {
            throw ValidationException::withMessages([
                'month' => ['A mess can have maximum 2 managers in a month.'],
            ]);
        }

        $assignment = MessManagerModel::query()->create([
            'mess_id' => $messId,
            'user_id' => $member->id,
            'month' => $data['month'],
        ]);

        if ($member->role === 'member') {
            $member->forceFill(['role' => 'manager'])->save();
        }

        return response()->json([
            'message' => 'Manager assigned for month.',
            'data' => $assignment->load('user:id,name,email,mobile,room_no,member_code,role,status'),
        ], 201);
    }

    public function managersRemove(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $assignment = MessManagerModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail();

        $userId = $assignment->user_id;
        $assignment->delete();

        $stillManager = MessManagerModel::query()
            ->where('user_id', $userId)
            ->where('month', '>=', now()->format('Y-m'))
            ->exists();

        $user = UserModel::query()->find($userId);

        if ($user && $user->role === 'manager' && ! $stillManager) {
            $user->forceFill(['role' => 'member'])->save();
        }

        return response()->json([
            'message' => 'Manager assignment removed.',
        ]);
    }
}
