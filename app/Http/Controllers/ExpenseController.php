<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\ExpenseEntryModel;
use App\Models\MessModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    use ResolvesMessId;

    public function expensesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, [
            'total_amount' => 0,
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
        ])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'fund_type' => ['sometimes', Rule::in(['mess_fund', 'manager_fund'])],
            'category' => ['sometimes', Rule::in(['market', 'constant', 'feast', 'tour', 'other'])],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
        ]);

        $query = ExpenseEntryModel::query()
            ->with(['recorder:id,name'])
            ->where('mess_id', $messId);

        if (! empty($data['fund_type'])) {
            $query->where('fund_type', $data['fund_type']);
        }

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        if (! empty($data['from_date']) || ! empty($data['to_date'])) {
            $query->whereBetween('spent_on', [
                $data['from_date'] ?? now()->startOfMonth()->toDateString(),
                $data['to_date'] ?? now()->toDateString(),
            ]);
        }

        $total = (float) (clone $query)->sum('amount');
        $rows = $query->latest('spent_on')->paginate(20);

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

    public function expensesCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'fund_type' => ['required', Rule::in(['mess_fund', 'manager_fund'])],
            'category' => ['required', Rule::in(['market', 'constant', 'feast', 'tour', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'spent_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['fund_type'] === 'mess_fund' && ! $mess->hasMessFund()) {
            throw ValidationException::withMessages([
                'fund_type' => ['This mess has only manager fund.'],
            ]);
        }

        if (in_array($data['category'], ['feast', 'tour'], true) && $mess->hasMessFund()) {
            $data['fund_type'] = 'mess_fund';
        }

        $row = ExpenseEntryModel::query()->create([
            'mess_id' => $messId,
            'fund_type' => $data['fund_type'],
            'category' => $data['category'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'spent_on' => $data['spent_on'],
            'note' => $data['note'] ?? null,
            'recorded_by' => $actor->id,
        ]);

        return response()->json([
            'message' => 'Expense recorded.',
            'data' => $row->load(['recorder:id,name']),
        ], 201);
    }

    public function expensesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        ExpenseEntryModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Expense deleted.',
        ]);
    }
}
