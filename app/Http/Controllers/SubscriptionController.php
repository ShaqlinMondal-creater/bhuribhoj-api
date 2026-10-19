<?php

namespace App\Http\Controllers;

use App\Models\MessSubscriptionModel;
use App\Models\PaymentLogModel;
use App\Models\PlanModel;
use App\Services\RazorpayService;
use App\Support\AccountSeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly RazorpayService $razorpay,
    ) {}

    public function subscriptionsOrder(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            throw ValidationException::withMessages([
                'plan_id' => ['This account cannot buy a mess subscription.'],
            ]);
        }

        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
        ]);

        $plan = PlanModel::query()->where('is_active', true)->find($data['plan_id']);
        if (! $plan) {
            throw ValidationException::withMessages([
                'plan_id' => ['This plan is not available.'],
            ]);
        }

        $current = AccountSeat::currentSubscription($user);
        if ($current && (int) $current->plan_id === (int) $plan->id) {
            throw ValidationException::withMessages([
                'plan_id' => ['This is already the current plan.'],
            ]);
        }

        $amountPaise = ((int) $plan->price_inr) * 100;
        if ($amountPaise < 100) {
            throw ValidationException::withMessages([
                'plan_id' => ['This plan amount is too small for Razorpay.'],
            ]);
        }

        MessSubscriptionModel::query()
            ->where('purchased_by', $user->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        try {
            $order = $this->razorpay->createOrder($amountPaise, Str::limit('bb'.$user->id.'t'.time(), 40, ''), [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $subscription = MessSubscriptionModel::query()->create([
            'mess_id' => null,
            'plan_id' => $plan->id,
            'purchase_source' => 'self_purchase',
            'purchased_by' => $user->id,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->toDateString(),
            'status' => 'pending',
            'amount_paid_inr' => $plan->price_inr,
            'razorpay_order_id' => $order['id'],
        ]);

        $this->logPayment($user->id, $subscription, 'created');

        return response()->json([
            'key' => $this->razorpay->key(),
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'],
            'subscription_id' => $subscription->id,
            'plan' => $plan->only(['id', 'name', 'slug', 'duration_months', 'price_inr']),
        ]);
    }

    public function subscriptionsFail(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['sometimes', 'nullable', 'string'],
            'failure_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $subscription = MessSubscriptionModel::query()
            ->where('purchased_by', $user->id)
            ->where('razorpay_order_id', $data['razorpay_order_id'])
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => ['This order does not belong to the signed-in account.'],
            ]);
        }

        $this->logPayment(
            $user->id,
            $subscription,
            'failed',
            $data['razorpay_payment_id'] ?? null,
            $data['failure_reason'] ?? null,
        );

        return response()->json([
            'message' => 'Payment attempt saved.',
        ]);
    }

    public function subscriptionsVerify(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $subscription = MessSubscriptionModel::query()
            ->where('purchased_by', $user->id)
            ->where('razorpay_order_id', $data['razorpay_order_id'])
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => ['This order does not belong to the signed-in account.'],
            ]);
        }

        if ($subscription->status === 'active') {
            return response()->json([
                'message' => 'Payment already confirmed.',
                'subscription' => $subscription->load('plan:id,name,slug,duration_months,price_inr'),
                'seat' => AccountSeat::for($user),
            ]);
        }

        try {
            $valid = $this->razorpay->signatureIsValid(
                $data['razorpay_order_id'],
                $data['razorpay_payment_id'],
                $data['razorpay_signature'],
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        if (! $valid) {
            $this->logPayment($user->id, $subscription, 'failed', $data['razorpay_payment_id'], 'Payment signature did not match.');
            throw ValidationException::withMessages([
                'razorpay_signature' => ['Payment signature did not match.'],
            ]);
        }

        try {
            $payment = $this->razorpay->fetchPayment($data['razorpay_payment_id']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $plan = PlanModel::query()->findOrFail($subscription->plan_id);
        $paid = (int) ($payment['amount'] ?? 0);
        $captured = in_array($payment['status'] ?? '', ['captured', 'authorized'], true);

        if (! $captured || ($payment['order_id'] ?? '') !== $data['razorpay_order_id'] || $paid !== ((int) $plan->price_inr) * 100) {
            $this->logPayment($user->id, $subscription, 'failed', $data['razorpay_payment_id'], 'Razorpay has not captured this payment.');
            throw ValidationException::withMessages([
                'razorpay_payment_id' => ['Razorpay has not captured this payment.'],
            ]);
        }

        $startsOn = now()->startOfDay();

        $subscription->forceFill([
            'mess_id' => $user->mess_id,
            'status' => 'active',
            'razorpay_payment_id' => $data['razorpay_payment_id'],
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $startsOn->copy()->addMonths($plan->duration_months)->toDateString(),
            'amount_paid_inr' => $plan->price_inr,
        ])->save();

        MessSubscriptionModel::query()
            ->where('purchased_by', $user->id)
            ->where('id', '!=', $subscription->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $this->logPayment($user->id, $subscription, 'paid', $data['razorpay_payment_id']);

        return response()->json([
            'message' => 'Payment received. Fill the mess details.',
            'subscription' => $subscription->fresh()->load('plan:id,name,slug,duration_months,price_inr'),
            'seat' => AccountSeat::for($user->fresh()),
        ]);
    }

    private function logPayment(
        int $userId,
        MessSubscriptionModel $subscription,
        string $status,
        ?string $paymentId = null,
        ?string $reason = null,
    ): void {
        PaymentLogModel::query()->create([
            'user_id' => $userId,
            'mess_subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'razorpay_order_id' => $subscription->razorpay_order_id,
            'razorpay_payment_id' => $paymentId,
            'amount_inr' => (int) $subscription->amount_paid_inr,
            'status' => $status,
            'failure_reason' => $reason,
        ]);
    }
}
