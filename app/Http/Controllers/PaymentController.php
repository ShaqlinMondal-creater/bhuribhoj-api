<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\PaymentLogModel;
use App\Rules\KolkataDateRule;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    use ResolvesMessId;

    public function paymentsFetch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['created', 'failed', 'paid'])],
            'from_date' => ['sometimes', 'nullable', new KolkataDateRule],
            'to_date' => ['sometimes', 'nullable', new KolkataDateRule],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = PaymentLogModel::query()->with([
            'user:id,name,email',
            'plan:id,name,duration_months,price_inr',
        ]);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $from = Carbon::createFromFormat('d-m-Y', $filters['from_date'], 'Asia/Kolkata')->startOfDay();
            $query->where('created_at', '>=', $from->format('Y-m-d H:i:s'));
        }

        if (! empty($filters['to_date'])) {
            $to = Carbon::createFromFormat('d-m-Y', $filters['to_date'], 'Asia/Kolkata')->endOfDay();
            if (! empty($filters['from_date'])) {
                $from = Carbon::createFromFormat('d-m-Y', $filters['from_date'], 'Asia/Kolkata')->startOfDay();
                if ($to->lt($from)) {
                    throw ValidationException::withMessages([
                        'to_date' => ['The end date must be on or after the start date.'],
                    ]);
                }
            }
            $query->where('created_at', '<=', $to->format('Y-m-d H:i:s'));
        }

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('razorpay_order_id', 'like', $like)
                    ->orWhere('razorpay_payment_id', 'like', $like)
                    ->orWhereHas('user', function ($user) use ($like) {
                        $user->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    })
                    ->orWhereHas('plan', function ($plan) use ($like) {
                        $plan->where('name', 'like', $like);
                    });
            });
        }

        $count = (clone $query)->count();
        $rows = $query
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
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
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
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
            'message' => 'Bill deleted.',
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
