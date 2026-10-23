<?php

namespace App\Http\Controllers;

use App\Models\DailyMealModel;
use App\Models\DepositModel;
use App\Models\MessModel;
use App\Models\MessSubscriptionModel;
use App\Models\PaymentLogModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function usersFetch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role' => ['sometimes', 'nullable', Rule::in(['admin', 'president', 'manager', 'member'])],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        $search = trim((string) ($filters['search'] ?? ''));

        $query = UserModel::query();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $count = (clone $query)->count();

        $rows = $query
            ->with('mess:id,name')
            ->orderBy('role')
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get([
                'id',
                'mess_id',
                'name',
                'email',
                'mobile',
                'room_no',
                'member_code',
                'role',
                'status',
                'joining_date',
            ]);

        return response()->json([
            'data' => $rows->map(fn (UserModel $user) => $this->present($user))->values(),
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function usersUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->integer('id'))],
            'mobile' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(['admin', 'president', 'manager', 'member'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $user = UserModel::query()->with('mess:id,name')->find($data['id']);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['That account was not found.'],
            ]);
        }

        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ['You cannot change your own role.'],
            ]);
        }

        if ($user->id === $request->user()->id && $data['status'] === 'inactive') {
            throw ValidationException::withMessages([
                'status' => ['You cannot mark your own account inactive.'],
            ]);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'],
        ])->save();

        return response()->json([
            'message' => 'User updated.',
            'data' => $this->present($user),
        ]);
    }

    public function usersDelete(int $id, Request $request): JsonResponse
    {
        $user = UserModel::query()->find($id);

        if (! $user) {
            throw ValidationException::withMessages([
                'id' => ['That account was not found.'],
            ]);
        }

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'id' => ['You cannot delete the account you are signed in with.'],
            ]);
        }

        if ($this->isInUse($user)) {
            throw ValidationException::withMessages([
                'id' => ['This account is still used by a mess, so it cannot be deleted.'],
            ]);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'User deleted.',
        ]);
    }

    private function isInUse(UserModel $user): bool
    {
        $id = $user->id;

        return MessModel::query()->where('president_user_id', $id)->orWhere('manager_user_id', $id)->exists()
            || PaymentLogModel::query()->where('user_id', $id)->exists()
            || DepositModel::query()->where('user_id', $id)->orWhere('added_by', $id)->exists()
            || DailyMealModel::query()->where('user_id', $id)->exists()
            || MessSubscriptionModel::query()->where('purchased_by', $id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(UserModel $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'room_no' => $user->room_no,
            'member_code' => $user->member_code,
            'role' => $user->role,
            'status' => $user->status,
            'joining_date' => $user->joining_date,
            'mess' => $user->mess ? [
                'id' => $user->mess->id,
                'name' => $user->mess->name,
            ] : null,
        ];
    }
}
