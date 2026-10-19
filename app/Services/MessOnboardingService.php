<?php

namespace App\Services;

use App\Models\MessInvitationModel;
use App\Models\MessModel;
use App\Models\MessSubscriptionModel;
use App\Models\PlanModel;
use App\Models\UserModel;
use App\Support\MemberCodeGenerator;
use App\Support\MessCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessOnboardingService
{
    /**
     * @param  array<string, mixed>  $messData
     * @param  array<string, mixed>  $presidentData
     * @return array{mess: MessModel, president: UserModel, subscription: MessSubscriptionModel}
     */
    public function provision(
        PlanModel $plan,
        array $messData,
        array $presidentData,
        string $purchaseSource,
        ?UserModel $purchasedBy = null,
        ?MessInvitationModel $invitation = null,
    ): array {
        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['This plan is not available.'],
            ]);
        }

        if (UserModel::query()->where('email', $presidentData['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        return DB::transaction(function () use ($plan, $messData, $presidentData, $purchaseSource, $purchasedBy, $invitation) {
            $mess = MessModel::query()->create([
                'name' => $messData['name'],
                'code' => $messData['code'] ?? MessCodeGenerator::fromName($messData['name']),
                'address' => $messData['address'],
                'district' => $messData['district'],
                'pincode' => $messData['pincode'],
                'contact_no' => $messData['contact_no'],
                'email' => $messData['email'],
                'fund_mode' => $messData['fund_mode'] ?? 'manager_only',
                'guest_chicken_price' => 90,
                'guest_fish_price' => 70,
                'guest_egg_price' => 50,
                'guest_veg_price' => 45,
                'is_active' => true,
            ]);

            $president = UserModel::query()->create([
                'mess_id' => $mess->id,
                'name' => $presidentData['name'],
                'email' => $presidentData['email'],
                'mobile' => $presidentData['mobile'] ?? null,
                'room_no' => $presidentData['room_no'] ?? null,
                'member_code' => MemberCodeGenerator::next($mess),
                'password' => $presidentData['password'],
                'role' => 'president',
                'status' => 'active',
                'joining_date' => now(),
                'is_loggedin' => false,
            ]);

            $startsOn = now()->startOfDay();
            $endsOn = $startsOn->copy()->addMonths($plan->duration_months);

            $subscription = MessSubscriptionModel::query()->create([
                'mess_id' => $mess->id,
                'plan_id' => $plan->id,
                'purchase_source' => $purchaseSource,
                'purchased_by' => $purchasedBy?->id ?? $president->id,
                'invitation_id' => $invitation?->id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'status' => 'active',
                'amount_paid_inr' => $plan->price_inr,
            ]);

            if ($invitation) {
                $invitation->forceFill([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'mess_name' => $mess->name,
                ])->save();
            }

            return [
                'mess' => $mess->fresh(),
                'president' => $president->fresh()->load('mess:id,name,code'),
                'subscription' => $subscription->fresh()->load('plan'),
            ];
        });
    }
}
