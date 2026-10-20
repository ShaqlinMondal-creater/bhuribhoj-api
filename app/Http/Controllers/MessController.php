<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\MessModel;
use App\Models\MessSubscriptionModel;
use App\Support\AccountSeat;
use App\Support\MemberCodeGenerator;
use App\Support\MessCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessController extends Controller
{
    use ResolvesMessId;

    public function messesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor->role === 'admin') {
            $messes = MessModel::query()->latest()->paginate(20);

            return response()->json($messes);
        }

        if ($blank = $this->blankMessResponse($actor, ['data' => null])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->with('activeSubscription.plan')->findOrFail($messId);

        return response()->json([
            'data' => $mess,
        ]);
    }

    public function messesOpen(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor->role !== 'president' || $actor->mess_id) {
            throw ValidationException::withMessages([
                'mess' => ['This account cannot open a mess.'],
            ]);
        }

        $subscription = MessSubscriptionModel::query()
            ->where('purchased_by', $actor->id)
            ->whereNull('mess_id')
            ->where('status', 'active')
            ->whereDate('ends_on', '>=', now()->toDateString())
            ->latest('id')
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'mess' => ['Buy a subscription before saving the mess details.'],
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'unique:messes,code'],
            'address' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'string', 'max:12'],
            'contact_no' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255'],
            'fund_mode' => ['sometimes', Rule::in(['manager_only', 'mess_and_manager'])],
        ]);

        $mess = DB::transaction(function () use ($actor, $subscription, $data) {
            $mess = MessModel::query()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?? MessCodeGenerator::fromName($data['name']),
                'address' => $data['address'],
                'district' => $data['district'],
                'pincode' => $data['pincode'],
                'contact_no' => $data['contact_no'],
                'email' => $data['email'],
                'fund_mode' => $data['fund_mode'] ?? 'manager_only',
                'guest_chicken_price' => 90,
                'guest_fish_price' => 70,
                'guest_egg_price' => 50,
                'guest_veg_price' => 45,
                'is_active' => true,
            ]);

            $subscription->forceFill(['mess_id' => $mess->id])->save();

            $actor->forceFill([
                'mess_id' => $mess->id,
                'member_code' => MemberCodeGenerator::next($mess),
                'joining_date' => $actor->joining_date ?? now(),
            ])->save();

            return $mess;
        });

        return response()->json([
            'message' => 'Mess created.',
            'data' => $mess->fresh()->load('activeSubscription.plan'),
            'seat' => AccountSeat::for($actor->fresh()),
        ], 201);
    }

    public function messesUpdate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'district' => ['sometimes', 'string', 'max:120'],
            'pincode' => ['sometimes', 'string', 'max:12'],
            'contact_no' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'email', 'max:255'],
            'fund_mode' => ['sometimes', Rule::in(['manager_only', 'mess_and_manager'])],
            'monthly_due_amount' => ['sometimes', 'numeric', 'min:0'],
            'monthly_due_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'late_fee_amount' => ['sometimes', 'numeric', 'min:0'],
            'guest_chicken_price' => ['sometimes', 'numeric', 'min:0'],
            'guest_fish_price' => ['sometimes', 'numeric', 'min:0'],
            'guest_egg_price' => ['sometimes', 'numeric', 'min:0'],
            'guest_veg_price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'mess' => ['No fields to update.'],
            ]);
        }

        $mess->fill($data)->save();

        return response()->json([
            'message' => 'Mess updated.',
            'data' => $mess->fresh(),
        ]);
    }
}
