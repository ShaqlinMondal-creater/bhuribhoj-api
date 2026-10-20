<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MessRuleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessRuleController extends Controller
{
    use ResolvesMessId;

    public function messRulesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $rows = MessRuleModel::query()
            ->where('mess_id', $messId)
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }

    public function messRulesCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fine_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $row = MessRuleModel::query()->create([
            'mess_id' => $messId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'fine_amount' => $data['fine_amount'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Mess rule created.',
            'data' => $row,
        ], 201);
    }

    public function messRulesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        MessRuleModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Mess rule deleted.',
        ]);
    }
}
