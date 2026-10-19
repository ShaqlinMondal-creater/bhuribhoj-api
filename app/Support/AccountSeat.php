<?php

namespace App\Support;

use App\Models\MessSubscriptionModel;
use App\Models\UserModel;

class AccountSeat
{
    /**
     * @return array{mess: bool, subscription: bool, next: string, subscription_ends_on: ?string, plan_id: ?int}
     */
    public static function for(UserModel $user): array
    {
        if ($user->role === 'admin') {
            return [
                'mess' => false,
                'subscription' => false,
                'next' => 'admin',
                'subscription_ends_on' => null,
                'plan_id' => null,
            ];
        }

        $subscription = self::currentSubscription($user);

        $hasMess = $user->mess_id !== null;
        $hasSubscription = $subscription !== null;

        return [
            'mess' => $hasMess,
            'subscription' => $hasSubscription,
            'next' => $hasSubscription ? 'dashboard' : 'plans',
            'subscription_ends_on' => $subscription?->ends_on?->toDateString(),
            'plan_id' => $subscription ? (int) $subscription->plan_id : null,
        ];
    }

    public static function currentSubscription(UserModel $user): ?MessSubscriptionModel
    {
        $query = MessSubscriptionModel::query()
            ->where('status', 'active')
            ->whereDate('ends_on', '>=', now()->toDateString());

        if ($user->mess_id) {
            return (clone $query)->where('mess_id', $user->mess_id)->latest('id')->first();
        }

        return $query
            ->where('purchased_by', $user->id)
            ->whereNull('mess_id')
            ->latest('id')
            ->first();
    }
}
