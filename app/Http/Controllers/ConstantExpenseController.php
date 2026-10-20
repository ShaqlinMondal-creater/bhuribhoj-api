<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\ConstantExpenseModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConstantExpenseController extends Controller
{
    use ResolvesMessId;

    public function constantExpensesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $rows = ConstantExpenseModel::query()
            ->where('mess_id', $messId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function constantExpensesCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'fund_type' => ['sometimes', Rule::in(['mess_fund', 'manager_fund'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = ConstantExpenseModel::query()->create([
            'mess_id' => $messId,
            'name' => $data['name'],
            'amount' => $data['amount'],
            'fund_type' => $data['fund_type'] ?? 'manager_fund',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Constant expense created.',
            'data' => $row,
        ], 201);
    }

    public function constantExpensesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        ConstantExpenseModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Constant expense deleted.',
        ]);
    }
}
