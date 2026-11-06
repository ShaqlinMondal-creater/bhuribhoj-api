<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

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

    /**
     * @return array{coupon: self, discount_inr: int, amount_inr: int}
     */
    public static function applyToOrder(string $code, int $userId, int $orderInr): array
    {
        $coupon = self::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();

        if (! $coupon || ! $coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not valid.'],
            ]);
        }

        $today = Carbon::now('Asia/Kolkata')->startOfDay();

        if ($coupon->starts_on && $coupon->starts_on->gt($today)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon is not active yet.'],
            ]);
        }

        if ($coupon->ends_on && $coupon->ends_on->lt($today)) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has expired.'],
            ]);
        }

        if ($coupon->usage_limit !== null && (int) $coupon->used_count >= (int) $coupon->usage_limit) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon has reached its usage limit.'],
            ]);
        }

        if ($coupon->min_order_inr !== null && $orderInr < (int) $coupon->min_order_inr) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon needs a higher plan price.'],
            ]);
        }

        if ($coupon->per_user_limit !== null) {
            $usedByUser = MessSubscriptionModel::query()
                ->where('coupon_id', $coupon->id)
                ->where('purchased_by', $userId)
                ->whereIn('status', ['active', 'expired', 'pending'])
                ->count();

            if ($usedByUser >= (int) $coupon->per_user_limit) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['You already used this coupon the allowed number of times.'],
                ]);
            }
        }

        $discount = $coupon->discountAmount($orderInr);

        if ($discount <= 0) {
            throw ValidationException::withMessages([
                'coupon_code' => ['This coupon does not change the price.'],
            ]);
        }

        return [
            'coupon' => $coupon,
            'discount_inr' => $discount,
            'amount_inr' => max(0, $orderInr - $discount),
        ];
    }

    public function discountAmount(int $orderInr): int
    {
        if ($this->discount_type === 'percent') {
            $discount = (int) floor(($orderInr * (int) $this->discount_value) / 100);
            if ($this->max_discount_inr !== null) {
                $discount = min($discount, (int) $this->max_discount_inr);
            }

            return max(0, min($discount, $orderInr));
        }

        return max(0, min((int) $this->discount_value, $orderInr));
    }
}
