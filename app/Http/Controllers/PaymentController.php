<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\PaymentLogModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    use ResolvesMessId;

    public function paymentsFetch(): JsonResponse
    {
        $rows = PaymentLogModel::query()
            ->with([
                'user:id,name,email',
                'plan:id,name,duration_months,price_inr',
            ])
            ->orderByDesc('id')
            ->get([
                'id',
                'user_id',
                'mess_subscription_id',
                'plan_id',
                'razorpay_order_id',
                'razorpay_payment_id',
                'amount_inr',
                'status',
                'failure_reason',
                'created_at',
            ]);

        return response()->json([
            'data' => $rows->map(fn (PaymentLogModel $row) => $this->present($row))->values(),
        ]);
    }

    public function billsFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($blank = $this->blankMessResponse($actor, ['data' => []])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);

        $rows = PaymentLogModel::query()
            ->whereHas('subscription', function ($query) use ($messId) {
                $query->where('mess_id', $messId);
            })
            ->with([
                'user:id,name,email',
                'plan:id,name,duration_months,price_inr',
            ])
            ->orderByDesc('id')
            ->get([
                'id',
                'user_id',
                'mess_subscription_id',
                'plan_id',
                'razorpay_order_id',
                'razorpay_payment_id',
                'amount_inr',
                'status',
                'failure_reason',
                'created_at',
            ]);

        return response()->json([
            'data' => $rows->map(fn (PaymentLogModel $row) => $this->present($row))->values(),
        ]);
    }

    public function paymentsDelete(int $id): JsonResponse
    {
        $row = PaymentLogModel::query()->find($id);

        if (! $row) {
            throw ValidationException::withMessages([
                'id' => ['That payment was not found.'],
            ]);
        }

        $row->delete();

        return response()->json([
            'message' => 'Payment deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(PaymentLogModel $row): array
    {
        return [
            'id' => $row->id,
            'amount_inr' => $row->amount_inr,
            'status' => $row->status,
            'razorpay_order_id' => $row->razorpay_order_id,
            'razorpay_payment_id' => $row->razorpay_payment_id,
            'failure_reason' => $row->failure_reason,
            'mess_subscription_id' => $row->mess_subscription_id,
            'created_at' => $row->created_at,
            'user' => $row->user ? [
                'id' => $row->user->id,
                'name' => $row->user->name,
                'email' => $row->user->email,
            ] : null,
            'plan' => $row->plan ? [
                'id' => $row->plan->id,
                'name' => $row->plan->name,
                'duration_months' => $row->plan->duration_months,
                'price_inr' => $row->plan->price_inr,
            ] : null,
        ];
    }
}
