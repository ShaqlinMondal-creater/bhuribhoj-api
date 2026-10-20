<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DepositModel;
use App\Models\MessModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            'fund_type' => ['sometimes', Rule::in(['mess_fund', 'manager_fund'])],
            'deposit_type' => ['sometimes', Rule::in(['join', 'monthly', 'extra'])],
            'for_month' => ['sometimes', 'regex:/^\d{4}-\d{2}$/'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $query = DepositModel::query()
            ->with(['user:id,name,email,member_code,role', 'recorder:id,name'])
            ->where('mess_id', $messId);

        if ($actor->role === 'member') {
            $query->where('user_id', $actor->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['fund_type'])) {
            $query->where('fund_type', $filters['fund_type']);
        }

        if (! empty($filters['deposit_type'])) {
            $query->where('deposit_type', $filters['deposit_type']);
        }

        if (! empty($filters['for_month'])) {
            $query->where('for_month', $filters['for_month']);
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
            'deposited_on' => ['required', 'date'],
            'for_month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['fund_type'] === 'mess_fund' && ! $mess->hasMessFund()) {
            throw ValidationException::withMessages([
                'fund_type' => ['This mess has only manager fund.'],
            ]);
        }

        $member = UserModel::query()
            ->where('id', $data['user_id'])
            ->where('mess_id', $messId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['User does not belong to this mess.'],
            ]);
        }

        if ($actor->role === 'member' && (int) $actor->id !== (int) $member->id) {
            abort(403, 'Members can only add their own deposits.');
        }

        if ($actor->role === 'member' && $data['deposit_type'] === 'join') {
            throw ValidationException::withMessages([
                'deposit_type' => ['Only staff can record join deposits.'],
            ]);
        }

        $forMonth = $data['for_month'] ?? (
            in_array($data['deposit_type'], ['monthly', 'extra'], true)
                ? date('Y-m', strtotime($data['deposited_on']))
                : null
        );

        $deposit = DepositModel::query()->create([
            'mess_id' => $messId,
            'user_id' => $member->id,
            'fund_type' => $data['fund_type'],
            'deposit_type' => $data['deposit_type'],
            'amount' => $data['amount'],
            'deposited_on' => $data['deposited_on'],
            'for_month' => $forMonth,
            'note' => $data['note'] ?? null,
            'recorded_by' => $actor->id,
        ]);

        return response()->json([
            'message' => 'Deposit recorded.',
            'data' => $deposit->load(['user:id,name,email,member_code,role', 'recorder:id,name']),
        ], 201);
    }
}
