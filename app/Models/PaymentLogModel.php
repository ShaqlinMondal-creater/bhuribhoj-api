<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentLogModel extends Model
{
    protected $table = 'payment_logs';

    protected $fillable = [
        'user_id',
        'mess_subscription_id',
        'plan_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'amount_inr',
        'status',
        'failure_reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(MessSubscriptionModel::class, 'mess_subscription_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }
}
