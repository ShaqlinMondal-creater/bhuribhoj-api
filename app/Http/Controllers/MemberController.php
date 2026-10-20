<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MessModel;
use App\Models\UserModel;
use App\Support\MemberCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    use ResolvesMessId;

    public function membersFetch(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($blank = $this->blankMessResponse($user, [
            'data' => [],
            'count' => 0,
            'limit' => (int) $request->input('limit', 10),
            'offset' => (int) $request->input('offset', 0),
            'rooms' => [],
        ])) {
            return $blank;
        }

        $messId = $this->resolveMessId($user, $request);

        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role' => ['sometimes', 'nullable', Rule::in(['president', 'manager', 'member'])],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'room_no' => ['sometimes', 'nullable', 'string', 'max:32'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;
        $search = trim((string) ($filters['search'] ?? ''));

        $query = UserModel::query()
            ->where('mess_id', $messId)
            ->whereIn('role', ['president', 'manager', 'member']);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like, $search) {
                $inner->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('member_code', 'like', $like);

                if (ctype_digit($search)) {
                    $inner->orWhere('id', (int) $search);
                }
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['room_no'])) {
            $query->where('room_no', $filters['room_no']);
        }

        $count = (clone $query)->count();

        $columns = [
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
            'is_loggedin',
            'created_at',
            'updated_at',
        ];

        $rows = $query
            ->orderBy('role')
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get($columns);

        $rooms = UserModel::query()
            ->where('mess_id', $messId)
            ->whereNotNull('room_no')
            ->where('room_no', '!=', '')
            ->distinct()
            ->orderBy('room_no')
            ->pluck('room_no')
            ->values();

        return response()->json([
            'data' => $rows,
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
            'rooms' => $rooms,
        ]);
    }

    public function membersCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'room_no' => ['nullable', 'string', 'max:32'],
            'role' => ['sometimes', Rule::in(['member', 'manager'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $role = $data['role'] ?? 'member';

        $member = UserModel::query()->create([
            'mess_id' => $mess->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'room_no' => $data['room_no'] ?? null,
            'member_code' => MemberCodeGenerator::next($mess),
            'password' => $data['password'],
            'role' => $role,
            'status' => $data['status'] ?? 'active',
            'joining_date' => now(),
            'is_loggedin' => false,
        ]);

        return response()->json([
            'message' => 'Member created.',
            'data' => $member,
        ], 201);
    }

    public function membersUpdate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'room_no' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $member = UserModel::query()
            ->where('id', $data['user_id'])
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($member->id === $actor->id && ($data['status'] ?? 'active') === 'inactive') {
            throw ValidationException::withMessages([
                'status' => ['You cannot mark your own seat inactive.'],
            ]);
        }

        $member->fill(collect($data)->except('user_id')->all())->save();

        return response()->json([
            'message' => 'Member updated.',
            'data' => $member->only([
                'id',
                'name',
                'email',
                'mobile',
                'room_no',
                'member_code',
                'role',
                'status',
            ]),
        ]);
    }
}
