<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\ActivityLogModel;
use App\Models\DailyMealModel;
use App\Models\DepositModel;
use App\Models\MealAwayModel;
use App\Models\MessMemberModel;
use App\Models\MessModel;
use App\Models\PaymentLogModel;
use App\Models\UserModel;
use App\Support\MemberCodeGenerator;
use App\Support\MessSeats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'manager_count' => 0,
            'manager_limit' => MessSeats::MAX_MANAGERS,
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
            $role = $filters['role'];
            $query->whereExists(function ($sub) use ($messId, $role) {
                $sub->selectRaw('1')
                    ->from('mess_members')
                    ->whereColumn('mess_members.user_id', 'users.id')
                    ->where('mess_members.mess_id', $messId);

                if ($role === 'president') {
                    $sub->where('mess_members.president', true);
                } elseif ($role === 'manager') {
                    $sub->where('mess_members.manager', true);
                } else {
                    $sub->where('mess_members.member', true)
                        ->where('mess_members.president', false)
                        ->where('mess_members.manager', false);
                }
            });
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

        $seatMap = MessMemberModel::query()
            ->where('mess_id', $messId)
            ->get()
            ->keyBy('user_id');

        $rooms = UserModel::query()
            ->where('mess_id', $messId)
            ->whereNotNull('room_no')
            ->where('room_no', '!=', '')
            ->distinct()
            ->orderBy('room_no')
            ->pluck('room_no')
            ->values();

        $managerCount = MessMemberModel::query()
            ->where('mess_id', $messId)
            ->where('manager', true)
            ->count();

        return response()->json([
            'data' => $rows->map(function (UserModel $row) use ($seatMap) {
                $seat = $seatMap->get($row->id);

                return [
                    'id' => $row->id,
                    'mess_id' => $row->mess_id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'mobile' => $row->mobile,
                    'room_no' => $row->room_no,
                    'member_code' => $row->member_code,
                    'role' => $row->role,
                    'status' => $row->status,
                    'joining_date' => $row->joining_date,
                    'is_loggedin' => $row->is_loggedin,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                    'seats' => $seat ? $seat->flags() : [
                        'president' => $row->role === 'president',
                        'manager' => $row->role === 'manager',
                        'member' => true,
                    ],
                ];
            })->values(),
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
            'rooms' => $rooms,
            'manager_count' => $managerCount,
            'manager_limit' => MessSeats::MAX_MANAGERS,
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
            'manager' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $asManager = (bool) ($data['manager'] ?? false);

        $member = DB::transaction(function () use ($mess, $data, $asManager) {
            $member = UserModel::query()->create([
                'mess_id' => $mess->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'] ?? null,
                'room_no' => $data['room_no'] ?? null,
                'member_code' => MemberCodeGenerator::next($mess),
                'password' => $data['password'],
                'role' => 'member',
                'status' => $data['status'] ?? 'active',
                'joining_date' => now(),
                'is_loggedin' => false,
            ]);

            MessSeats::ensure($mess->id, (int) $member->id, true);
            if ($asManager) {
                MessSeats::setManager($mess->id, (int) $member->id, true);
            } else {
                MessSeats::syncUserRole((int) $member->id);
                MessSeats::syncMessPointers($mess->fresh());
            }

            return $member->fresh();
        });

        ActivityLogModel::record(
            'member.create',
            'Member '.$member->name.' added to '.$mess->name.'.',
            $actor->id,
            'user',
            $member->id,
            $mess->id,
            ['role' => $member->role, 'member_code' => $member->member_code],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Member created.',
            'data' => $this->presentMember($member, $mess->id),
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

        $changes = collect($data)->except('user_id')->all();
        $member->fill($changes)->save();
        MessSeats::ensure($messId, (int) $member->id, true);

        ActivityLogModel::record(
            'member.update',
            'Member '.$member->name.' updated.',
            $actor->id,
            'user',
            $member->id,
            $messId,
            array_keys($changes),
            $request->ip(),
        );

        return response()->json([
            'message' => 'Member updated.',
            'data' => $this->presentMember($member->fresh(), $messId),
        ]);
    }

    public function membersSeats(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $actorFlags = MessSeats::flagsFor($messId, (int) $actor->id);
        if (! $actorFlags['president'] && $actor->role !== 'admin') {
            throw ValidationException::withMessages([
                'seats' => ['Only the mess president can change seats.'],
            ]);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'manager' => ['required', 'boolean'],
        ]);

        $targetId = (int) $data['user_id'];

        // President seat is only changed via mess config / admin assign.
        MessSeats::setManager($messId, $targetId, (bool) $data['manager']);

        $member = UserModel::query()->where('id', $targetId)->where('mess_id', $messId)->firstOrFail();

        ActivityLogModel::record(
            'member.seats',
            'Seats updated for '.$member->name.'.',
            $actor->id,
            'user',
            $member->id,
            $messId,
            [
                'manager' => (bool) $data['manager'],
            ],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Seats updated.',
            'data' => $this->presentMember($member->fresh(), $messId),
        ]);
    }

    public function membersDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $member = UserModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->whereIn('role', ['president', 'manager', 'member'])
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'id' => ['That member was not found in this mess.'],
            ]);
        }

        if ($member->id === $actor->id) {
            throw ValidationException::withMessages([
                'id' => ['You cannot delete your own seat.'],
            ]);
        }

        $flags = MessSeats::flagsFor($messId, (int) $member->id);
        if ($flags['president']) {
            throw ValidationException::withMessages([
                'id' => ['Transfer the president seat before deleting this person.'],
            ]);
        }

        if (PaymentLogModel::query()->where('user_id', $member->id)->exists()) {
            throw ValidationException::withMessages([
                'id' => ['This member still has payment history, so the account cannot be deleted. Mark them inactive instead.'],
            ]);
        }

        $name = $member->name;
        $role = $member->role;
        $code = $member->member_code;

        DB::transaction(function () use ($member, $messId) {
            DailyMealModel::query()->where('mess_id', $messId)->where('user_id', $member->id)->delete();
            MealAwayModel::query()->where('mess_id', $messId)->where('user_id', $member->id)->delete();
            DepositModel::query()->where('mess_id', $messId)->where('user_id', $member->id)->delete();
            DepositModel::query()->where('mess_id', $messId)->where('added_by', $member->id)->update(['added_by' => null]);

            MessSeats::removeUser($messId, (int) $member->id);

            $member->tokens()->delete();
            $member->delete();
        });

        ActivityLogModel::record(
            'member.delete',
            'Member '.$name.' removed from the mess.',
            $actor->id,
            'user',
            $id,
            $messId,
            ['role' => $role, 'member_code' => $code],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Member deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentMember(UserModel $member, int $messId): array
    {
        $flags = MessSeats::flagsFor($messId, (int) $member->id);

        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'mobile' => $member->mobile,
            'room_no' => $member->room_no,
            'member_code' => $member->member_code,
            'role' => $member->role,
            'status' => $member->status,
            'seats' => $flags,
        ];
    }
}
