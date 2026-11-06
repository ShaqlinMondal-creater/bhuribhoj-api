<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponModel extends Model
{
    protected $table = 'coupons';

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'max_discount_inr',
        'min_order_inr',
        'starts_on',
        'ends_on',
        'usage_limit',
        'used_count',
        'per_user_limit',
        'is_active',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
            'max_discount_inr' => 'integer',
            'min_order_inr' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'per_user_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }
}
