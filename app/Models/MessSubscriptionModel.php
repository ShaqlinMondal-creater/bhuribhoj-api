<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessSubscriptionModel extends Model
{
    protected $table = 'mess_subscriptions';

    protected $fillable = [
        'mess_id',
        'plan_id',
        'purchase_source',
        'purchased_by',
        'starts_on',
        'ends_on',
        'status',
        'amount_paid_inr',
        'razorpay_order_id',
        'razorpay_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount_paid_inr' => 'integer',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'purchased_by');
    }
}
