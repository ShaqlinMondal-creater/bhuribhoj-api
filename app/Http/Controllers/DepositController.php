<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DepositModel;
use App\Models\MessModel;
use App\Models\UserModel;
use App\Rules\KolkataDateRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepositController extends Controller
{
    use ResolvesMessId;

    public function depositsFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, [
            'total_amount' => 0,
            'data' => [],
            'count' => 0,
            'limit' => (int) $request->input('limit', 20),
            'offset' => (int) $request->input('offset', 0),
        ])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $filters = $request->validate([
            'user_id' => ['sometimes', 'integer'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'fund_type' => ['sometimes', 'nullable', Rule::in(['mess_fund', 'manager_fund'])],
            'deposit_type' => ['sometimes', 'nullable', Rule::in(['join', 'monthly', 'extra'])],
            'for_month' => ['sometimes', 'nullable', new KolkataDateRule],
            'from_date' => ['sometimes', 'nullable', new KolkataDateRule],
            'to_date' => ['sometimes', 'nullable', new KolkataDateRule],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (! empty($filters['from_date']) && ! empty($filters['to_date'])) {
            $from = Carbon::createFromFormat('d-m-Y', $filters['from_date'], 'Asia/Kolkata')->startOfDay();
            $to = Carbon::createFromFormat('d-m-Y', $filters['to_date'], 'Asia/Kolkata')->startOfDay();
            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'to_date' => ['The end date must be on or after the start date.'],
                ]);
            }
        }

        $query = DepositModel::query()
            ->with(['user:id,name,email,member_code,role', 'addedBy:id,name'])
            ->where('mess_id', $messId);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('user', function ($user) use ($like) {
                $user->where('name', 'like', $like)
                    ->orWhere('member_code', 'like', $like);
            });
        }

        if (! empty($filters['fund_type'])) {
            $query->where('fund_type', $filters['fund_type']);
        }

        if (! empty($filters['deposit_type'])) {
            $query->where('deposit_type', $filters['deposit_type']);
        }

        if (! empty($filters['for_month'])) {
            $month = Carbon::createFromFormat('d-m-Y', $filters['for_month'], 'Asia/Kolkata');
            $query->whereRaw($this->monthKey('for_month').' = ?', [$month->format('Ym')]);
        }

        if (! empty($filters['from_date'])) {
            $from = Carbon::createFromFormat('d-m-Y', $filters['from_date'], 'Asia/Kolkata');
            $query->whereRaw($this->dayKey('deposited_on').' >= ?', [$from->format('Ymd')]);
        }

        if (! empty($filters['to_date'])) {
            $to = Carbon::createFromFormat('d-m-Y', $filters['to_date'], 'Asia/Kolkata');
            $query->whereRaw($this->dayKey('deposited_on').' <= ?', [$to->format('Ymd')]);
        }

        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();
        $limit = $filters['limit'] ?? $filters['per_page'] ?? 20;
        $offset = $filters['offset'] ?? 0;

        $rows = $query
            ->latest('deposited_on')
            ->latest('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'total_amount' => round($total, 2),
            'data' => $rows,
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function depositsCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'fund_type' => ['required', Rule::in(['mess_fund', 'manager_fund'])],
            'deposit_type' => ['required', Rule::in(['join', 'monthly', 'extra'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'deposited_on' => ['required', new KolkataDateRule],
            'for_month' => ['nullable', new KolkataDateRule],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['fund_type'] === 'mess_fund' && ! $mess->hasMessFund()) {
            throw ValidationException::withMessages([
                'fund_type' => ['This mess has only manager fund.'],
            ]);
        }

        $member = $this->memberOfMess($messId, (int) $data['user_id']);

        $deposit = DepositModel::query()->create([
            'mess_id' => $messId,
            'user_id' => $member->id,
            'fund_type' => $data['fund_type'],
            'deposit_type' => $data['deposit_type'],
            'amount' => $data['amount'],
            'deposited_on' => $data['deposited_on'],
            'for_month' => $data['for_month'] ?? $this->monthDate($data['deposit_type'], $data['deposited_on']),
            'note' => $data['note'] ?? null,
            'added_by' => $actor->id,
        ]);

        return response()->json([
            'message' => 'Deposit recorded.',
            'data' => $deposit->load(['user:id,name,email,member_code,role', 'addedBy:id,name']),
        ], 201);
    }

    public function depositsUpdate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'id' => ['required', 'integer'],
            'user_id' => ['sometimes', 'integer', Rule::exists('users', 'id')],
            'fund_type' => ['sometimes', Rule::in(['mess_fund', 'manager_fund'])],
            'deposit_type' => ['sometimes', Rule::in(['join', 'monthly', 'extra'])],
            'amount' => ['sometimes', 'numeric', 'min:1'],
            'deposited_on' => ['sometimes', new KolkataDateRule],
            'for_month' => ['sometimes', 'nullable', new KolkataDateRule],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $deposit = $this->depositForMess($messId, (int) $data['id']);
        $changes = array_diff_key($data, ['id' => true]);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'id' => ['No fields to update.'],
            ]);
        }

        if (isset($changes['user_id'])) {
            $this->memberOfMess($messId, (int) $changes['user_id']);
        }

        $fundType = $changes['fund_type'] ?? $deposit->fund_type;

        if ($fundType === 'mess_fund' && ! $mess->hasMessFund()) {
            throw ValidationException::withMessages([
                'fund_type' => ['This mess has only manager fund.'],
            ]);
        }

        $deposit->fill($changes)->save();

        return response()->json([
            'message' => 'Deposit updated.',
            'data' => $deposit->fresh()->load(['user:id,name,email,member_code,role', 'addedBy:id,name']),
        ]);
    }

    public function depositsDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $deposit = $this->depositForMess($messId, $id);
        $deposit->delete();

        return response()->json([
            'message' => 'Deposit deleted.',
        ]);
    }

    private function memberOfMess(int $messId, int $userId): UserModel
    {
        $member = UserModel::query()
            ->where('id', $userId)
            ->where('mess_id', $messId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['User does not belong to this mess.'],
            ]);
        }

        return $member;
    }

    private function depositForMess(int $messId, int $id): DepositModel
    {
        $deposit = DepositModel::query()
            ->where('mess_id', $messId)
            ->where('id', $id)
            ->first();

        if (! $deposit) {
            throw ValidationException::withMessages([
                'id' => ['Deposit was not found for this mess.'],
            ]);
        }

        return $deposit;
    }

    private function dayKey(string $column): string
    {
        return $this->dateKey($column, true);
    }

    private function monthKey(string $column): string
    {
        return $this->dateKey($column, false);
    }

    private function dateKey(string $column, bool $withDay): string
    {
        if (! in_array($column, ['deposited_on', 'for_month'], true)) {
            throw new \InvalidArgumentException('Unexpected deposit date column.');
        }

        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $month = "substr({$column}, 7, 4) || substr({$column}, 4, 2)";

            return $withDay ? "({$month} || substr({$column}, 1, 2))" : "({$month})";
        }

        $month = "CONCAT(SUBSTRING({$column}, 7, 4), SUBSTRING({$column}, 4, 2))";

        return $withDay ? "CONCAT({$month}, SUBSTRING({$column}, 1, 2))" : $month;
    }

    private function monthDate(string $depositType, string $depositedOn): ?string
    {
        if (! in_array($depositType, ['monthly', 'extra'], true)) {
            return null;
        }

        return Carbon::createFromFormat('d-m-Y', $depositedOn, 'Asia/Kolkata')
            ->startOfMonth()
            ->format('d-m-Y');
    }
}
