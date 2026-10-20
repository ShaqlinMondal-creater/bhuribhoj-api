<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\DepositModel;
use App\Models\MessModel;
use App\Models\MonthlyDueModel;
use App\Models\UserModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonthlyDueController extends Controller
{
    use ResolvesMessId;

    public function monthlyDuesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, [
            'month' => now()->format('Y-m'),
            'summary' => [
                'total_amount' => 0,
                'total_late_fee' => 0,
                'pending' => 0,
                'paid' => 0,
                'overdue' => 0,
                'waived' => 0,
            ],
            'data' => [],
        ])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $filters = $request->validate([
            'month' => ['sometimes', 'regex:/^\d{4}-\d{2}$/'],
            'status' => ['sometimes', Rule::in(['pending', 'paid', 'overdue', 'waived'])],
            'user_id' => ['sometimes', 'integer'],
        ]);

        $month = $filters['month'] ?? now()->format('Y-m');

        $query = MonthlyDueModel::query()
            ->with(['user:id,name,email,member_code,role'])
            ->where('mess_id', $messId)
            ->where('month', $month)
            ->orderBy('id');

        if ($actor->role === 'member') {
            $query->where('user_id', $actor->id);
        } elseif (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $dues = $query->get();

        return response()->json([
            'month' => $month,
            'summary' => [
                'total_amount' => round((float) $dues->sum('amount'), 2),
                'total_late_fee' => round((float) $dues->sum('late_fee'), 2),
                'pending' => $dues->where('status', 'pending')->count(),
                'paid' => $dues->where('status', 'paid')->count(),
                'overdue' => $dues->where('status', 'overdue')->count(),
                'waived' => $dues->where('status', 'waived')->count(),
            ],
            'data' => $dues,
        ]);
    }

    public function monthlyDuesGenerate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        if ((float) $mess->monthly_due_amount <= 0) {
            throw ValidationException::withMessages([
                'month' => ['Set monthly_due_amount on mess before generating dues.'],
            ]);
        }

        [$year, $monthNum] = array_map('intval', explode('-', $data['month']));
        $dueDay = min((int) $mess->monthly_due_day, Carbon::create($year, $monthNum, 1)->daysInMonth);
        $dueOn = Carbon::create($year, $monthNum, $dueDay)->toDateString();

        $members = UserModel::query()
            ->where('mess_id', $messId)
            ->where('status', 'active')
            ->whereIn('role', ['president', 'manager', 'member'])
            ->get(['id']);

        $created = 0;
        $skipped = 0;

        foreach ($members as $member) {
            $exists = MonthlyDueModel::query()
                ->where('mess_id', $messId)
                ->where('user_id', $member->id)
                ->where('month', $data['month'])
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            MonthlyDueModel::query()->create([
                'mess_id' => $messId,
                'user_id' => $member->id,
                'month' => $data['month'],
                'amount' => $mess->monthly_due_amount,
                'due_on' => $dueOn,
                'late_fee' => 0,
                'status' => 'pending',
            ]);
            $created++;
        }

        return response()->json([
            'message' => 'Monthly dues generated.',
            'month' => $data['month'],
            'created' => $created,
            'skipped' => $skipped,
        ], 201);
    }

    public function monthlyDuesPay(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'due_id' => ['required', 'integer', Rule::exists('monthly_dues', 'id')],
            'deposited_on' => ['sometimes', 'date'],
            'fund_type' => ['sometimes', Rule::in(['mess_fund', 'manager_fund'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'apply_late_fee' => ['sometimes', 'boolean'],
        ]);

        $due = MonthlyDueModel::query()
            ->where('id', $data['due_id'])
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($actor->role === 'member' && (int) $actor->id !== (int) $due->user_id) {
            abort(403, 'Members can only pay their own dues.');
        }

        if (in_array($due->status, ['paid', 'waived'], true)) {
            throw ValidationException::withMessages([
                'due_id' => ['This due is already '.$due->status.'.'],
            ]);
        }

        $fundType = $data['fund_type'] ?? 'manager_fund';
        if ($fundType === 'mess_fund' && ! $mess->hasMessFund()) {
            throw ValidationException::withMessages([
                'fund_type' => ['This mess has only manager fund.'],
            ]);
        }

        $depositedOn = $data['deposited_on'] ?? now()->toDateString();
        $lateFee = (float) $due->late_fee;

        if (($data['apply_late_fee'] ?? true) && Carbon::parse($depositedOn)->gt(Carbon::parse($due->due_on))) {
            $lateFee = max($lateFee, (float) $mess->late_fee_amount);
        }

        $payAmount = (float) $due->amount + $lateFee;

        $result = DB::transaction(function () use ($due, $messId, $actor, $fundType, $depositedOn, $lateFee, $payAmount, $data) {
            $due->forceFill([
                'late_fee' => $lateFee,
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            $deposit = DepositModel::query()->create([
                'mess_id' => $messId,
                'user_id' => $due->user_id,
                'fund_type' => $fundType,
                'deposit_type' => 'monthly',
                'amount' => $payAmount,
                'deposited_on' => $depositedOn,
                'for_month' => $due->month,
                'note' => $data['note'] ?? 'Monthly due payment',
                'recorded_by' => $actor->id,
            ]);

            return [
                'due' => $due->fresh()->load('user:id,name,email,member_code,role'),
                'deposit' => $deposit->load(['user:id,name,member_code', 'recorder:id,name']),
            ];
        });

        return response()->json([
            'message' => 'Monthly due paid.',
            'data' => $result,
        ]);
    }

    public function monthlyDuesWaive(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'due_id' => ['required', 'integer', Rule::exists('monthly_dues', 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $due = MonthlyDueModel::query()
            ->where('id', $data['due_id'])
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($due->status === 'paid') {
            throw ValidationException::withMessages([
                'due_id' => ['Paid dues cannot be waived.'],
            ]);
        }

        $due->forceFill([
            'status' => 'waived',
            'late_fee' => 0,
        ])->save();

        return response()->json([
            'message' => 'Monthly due waived.',
            'data' => $due->fresh()->load('user:id,name,email,member_code,role'),
        ]);
    }

    public function monthlyDuesMarkOverdue(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $updated = MonthlyDueModel::query()
            ->where('mess_id', $messId)
            ->where('month', $data['month'])
            ->where('status', 'pending')
            ->whereDate('due_on', '<', now()->toDateString())
            ->update([
                'status' => 'overdue',
                'late_fee' => $mess->late_fee_amount,
            ]);

        return response()->json([
            'message' => 'Overdue dues marked.',
            'updated' => $updated,
        ]);
    }
}
