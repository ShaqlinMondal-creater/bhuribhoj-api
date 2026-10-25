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
            'fund_type' => ['sometimes', 'nullable', Rule::in(['mess_fund', 'manager_fund'])],
            'category' => ['sometimes', 'nullable', Rule::in(['market', 'constant', 'feast', 'tour', 'other'])],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
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

        if (! empty($data['from_date'])) {
            $query->whereDate('spent_on', '>=', $data['from_date']);
        }

        if (! empty($data['to_date'])) {
            $query->whereDate('spent_on', '<=', $data['to_date']);
        }

        $search = trim((string) ($data['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('title', 'like', $like)->orWhere('note', 'like', $like);
            });
        }

        $total = (float) (clone $query)->sum('amount');
        $rows = $query->latest('spent_on')->latest('id')->get();

        return response()->json([
            'total_amount' => round($total, 2),
            'data' => $rows,
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $rows->count(),
                'total' => $rows->count(),
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
            'quantity' => ['required', 'string', 'max:100'],
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
            'quantity' => $data['quantity'],
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

    public function expensesUpdate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'id' => ['required', 'integer'],
            'fund_type' => ['sometimes', Rule::in(['mess_fund', 'manager_fund'])],
            'category' => ['sometimes', Rule::in(['market', 'constant', 'feast', 'tour', 'other'])],
            'title' => ['sometimes', 'string', 'max:255'],
            'quantity' => ['sometimes', 'string', 'max:100'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'spent_on' => ['sometimes', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $row = ExpenseEntryModel::query()
            ->where('mess_id', $messId)
            ->where('id', $data['id'])
            ->first();

        if (! $row) {
            throw ValidationException::withMessages([
                'id' => ['Expense was not found for this mess.'],
            ]);
        }

        $changes = array_diff_key($data, ['id' => true]);

        if ($changes === []) {
            throw ValidationException::withMessages([
                'id' => ['No fields to update.'],
            ]);
        }

        $fundType = $changes['fund_type'] ?? $row->fund_type;
        $category = $changes['category'] ?? $row->category;

        if ($fundType === 'mess_fund' && ! $mess->hasMessFund()) {
            throw ValidationException::withMessages([
                'fund_type' => ['This mess has only manager fund.'],
            ]);
        }

        if (in_array($category, ['feast', 'tour'], true) && $mess->hasMessFund()) {
            $changes['fund_type'] = 'mess_fund';
        }

        $row->fill($changes)->save();

        return response()->json([
            'message' => 'Expense updated.',
            'data' => $row->fresh()->load(['recorder:id,name']),
        ]);
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
