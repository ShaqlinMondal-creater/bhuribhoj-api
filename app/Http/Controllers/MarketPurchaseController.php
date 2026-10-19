<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\ExpenseEntryModel;
use App\Models\MarketPurchaseModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketPurchaseController extends Controller
{
    use ResolvesMessId;

    public function marketPurchasesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'purchase_date' => ['sometimes', 'date'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date'],
        ]);

        $query = MarketPurchaseModel::query()
            ->with(['recorder:id,name'])
            ->where('mess_id', $messId);

        if (! empty($data['purchase_date'])) {
            $query->whereDate('purchase_date', $data['purchase_date']);
        } elseif (! empty($data['from_date']) || ! empty($data['to_date'])) {
            $query->whereBetween('purchase_date', [
                $data['from_date'] ?? now()->startOfMonth()->toDateString(),
                $data['to_date'] ?? now()->toDateString(),
            ]);
        }

        $rows = $query->latest('purchase_date')->paginate(20);

        return response()->json($rows);
    }

    public function marketPurchasesCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'purchase_date' => ['required', 'date'],
            'item_name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'create_expense' => ['sometimes', 'boolean'],
        ]);

        $purchase = MarketPurchaseModel::query()->create([
            'mess_id' => $messId,
            'purchase_date' => $data['purchase_date'],
            'item_name' => $data['item_name'],
            'quantity' => $data['quantity'] ?? null,
            'unit' => $data['unit'] ?? null,
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'recorded_by' => $actor->id,
        ]);

        if ($data['create_expense'] ?? true) {
            ExpenseEntryModel::query()->create([
                'mess_id' => $messId,
                'fund_type' => 'manager_fund',
                'category' => 'market',
                'title' => 'Market: '.$data['item_name'],
                'amount' => $data['amount'],
                'spent_on' => $data['purchase_date'],
                'note' => $data['note'] ?? null,
                'recorded_by' => $actor->id,
            ]);
        }

        return response()->json([
            'message' => 'Market purchase recorded.',
            'data' => $purchase->load('recorder:id,name'),
        ], 201);
    }

    public function marketPurchasesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        MarketPurchaseModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Market purchase deleted.',
        ]);
    }
}
