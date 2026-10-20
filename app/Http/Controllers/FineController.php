<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\FineModel;
use App\Models\MessRuleModel;
use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FineController extends Controller
{
    use ResolvesMessId;

    public function finesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'paid', 'waived'])],
            'user_id' => ['sometimes', 'integer'],
        ]);

        $query = FineModel::query()
            ->with(['user:id,name,member_code', 'rule:id,title', 'issuer:id,name'])
            ->where('mess_id', $messId);

        if ($actor->role === 'member') {
            $query->where('user_id', $actor->id);
        } elseif (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return response()->json([
            'data' => $query->latest('fined_on')->get(),
        ]);
    }

    public function finesCreate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'mess_rule_id' => ['nullable', 'integer', Rule::exists('mess_rules', 'id')],
            'reason' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'fined_on' => ['required', 'date'],
        ]);

        $member = UserModel::query()
            ->where('id', $data['user_id'])
            ->where('mess_id', $messId)
            ->first();

        if (! $member) {
            throw ValidationException::withMessages([
                'user_id' => ['User does not belong to this mess.'],
            ]);
        }

        $amount = $data['amount'] ?? null;

        if (! empty($data['mess_rule_id'])) {
            $rule = MessRuleModel::query()
                ->where('id', $data['mess_rule_id'])
                ->where('mess_id', $messId)
                ->first();

            if (! $rule) {
                throw ValidationException::withMessages([
                    'mess_rule_id' => ['Rule does not belong to this mess.'],
                ]);
            }

            $amount = $amount ?? (float) $rule->fine_amount;
        }

        if ($amount === null) {
            throw ValidationException::withMessages([
                'amount' => ['Amount is required when no rule is selected.'],
            ]);
        }

        $fine = FineModel::query()->create([
            'mess_id' => $messId,
            'user_id' => $member->id,
            'mess_rule_id' => $data['mess_rule_id'] ?? null,
            'reason' => $data['reason'],
            'amount' => $amount,
            'status' => 'pending',
            'fined_on' => $data['fined_on'],
            'issued_by' => $actor->id,
        ]);

        return response()->json([
            'message' => 'Fine issued.',
            'data' => $fine->load(['user:id,name,member_code', 'rule:id,title', 'issuer:id,name']),
        ], 201);
    }

    public function finesPay(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'fine_id' => ['required', 'integer', Rule::exists('fines', 'id')],
        ]);

        $fine = FineModel::query()
            ->where('id', $data['fine_id'])
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($actor->role === 'member' && (int) $actor->id !== (int) $fine->user_id) {
            abort(403, 'Members can only pay their own fines.');
        }

        if ($fine->status !== 'pending') {
            throw ValidationException::withMessages([
                'fine_id' => ['Fine is already '.$fine->status.'.'],
            ]);
        }

        $fine->forceFill(['status' => 'paid'])->save();

        return response()->json([
            'message' => 'Fine paid.',
            'data' => $fine->fresh()->load(['user:id,name,member_code', 'rule:id,title']),
        ]);
    }

    public function finesWaive(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'fine_id' => ['required', 'integer', Rule::exists('fines', 'id')],
        ]);

        $fine = FineModel::query()
            ->where('id', $data['fine_id'])
            ->where('mess_id', $messId)
            ->firstOrFail();

        if ($fine->status === 'paid') {
            throw ValidationException::withMessages([
                'fine_id' => ['Paid fines cannot be waived.'],
            ]);
        }

        $fine->forceFill(['status' => 'waived'])->save();

        return response()->json([
            'message' => 'Fine waived.',
            'data' => $fine->fresh()->load(['user:id,name,member_code', 'rule:id,title']),
        ]);
    }

    public function finesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        FineModel::query()
            ->where('id', $id)
            ->where('mess_id', $messId)
            ->firstOrFail()
            ->delete();

        return response()->json([
            'message' => 'Fine deleted.',
        ]);
    }
}
