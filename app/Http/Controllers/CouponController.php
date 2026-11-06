<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use App\Models\CouponModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function couponsFetch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = CouponModel::query()->with('creator:id,name');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('code', 'like', $like)
                    ->orWhere('note', 'like', $like);
            });
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        }

        if (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $count = (clone $query)->count();
        $rows = $query
            ->orderByDesc('id')
            ->skip($offset)
            ->take($limit)
            ->get();

        return response()->json([
            'data' => $rows,
            'count' => $count,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function couponsCreate(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['code'] = strtoupper($data['code']);
        $data['created_by'] = $request->user()->id;
        $data['used_count'] = 0;

        $coupon = CouponModel::query()->create($data);

        ActivityLogModel::record(
            'coupon.create',
            'Coupon '.$coupon->code.' created.',
            $request->user()->id,
            'coupon',
            $coupon->id,
            null,
            ['code' => $coupon->code],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Coupon created.',
            'data' => $coupon->load('creator:id,name'),
        ], 201);
    }

    public function couponsUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'code' => ['sometimes', 'string', 'max:40', Rule::unique('coupons', 'code')->ignore($request->integer('id'))],
            'discount_type' => ['sometimes', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['sometimes', 'integer', 'min:1'],
            'max_discount_inr' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_order_inr' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $coupon = CouponModel::query()->find($data['id']);

        if (! $coupon) {
            throw ValidationException::withMessages([
                'id' => ['Coupon was not found.'],
            ]);
        }

        $changes = array_intersect_key($data, array_flip([
            'code',
            'discount_type',
            'discount_value',
            'max_discount_inr',
            'min_order_inr',
            'starts_on',
            'ends_on',
            'usage_limit',
            'per_user_limit',
            'is_active',
            'note',
        ]));

        if ($changes === []) {
            throw ValidationException::withMessages([
                'code' => ['No fields to update.'],
            ]);
        }

        if (array_key_exists('code', $changes)) {
            $changes['code'] = strtoupper((string) $changes['code']);
        }

        $type = $changes['discount_type'] ?? $coupon->discount_type;
        $value = $changes['discount_value'] ?? $coupon->discount_value;
        $this->assertDiscount($type, (int) $value);

        $starts = array_key_exists('starts_on', $changes) ? $changes['starts_on'] : $coupon->starts_on?->toDateString();
        $ends = array_key_exists('ends_on', $changes) ? $changes['ends_on'] : $coupon->ends_on?->toDateString();
        $this->assertDateOrder($starts, $ends);

        $coupon->fill($changes)->save();

        ActivityLogModel::record(
            'coupon.update',
            'Coupon '.$coupon->code.' updated.',
            $request->user()->id,
            'coupon',
            $coupon->id,
            null,
            ['code' => $coupon->code],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Coupon updated.',
            'data' => $coupon->fresh()->load('creator:id,name'),
        ]);
    }

    public function couponsDelete(Request $request, int $id): JsonResponse
    {
        $coupon = CouponModel::query()->find($id);

        if (! $coupon) {
            throw ValidationException::withMessages([
                'id' => ['Coupon was not found.'],
            ]);
        }

        if ((int) $coupon->used_count > 0) {
            throw ValidationException::withMessages([
                'coupon' => ['This coupon was already used, so it cannot be deleted. Turn it off instead.'],
            ]);
        }

        $code = $coupon->code;
        $coupon->delete();

        ActivityLogModel::record(
            'coupon.delete',
            'Coupon '.$code.' deleted.',
            $request->user()->id,
            'coupon',
            $id,
            null,
            ['code' => $code],
            $request->ip(),
        );

        return response()->json([
            'message' => 'Coupon deleted.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'integer', 'min:1'],
            'max_discount_inr' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_order_inr' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'starts_on' => ['sometimes', 'nullable', 'date'],
            'ends_on' => ['sometimes', 'nullable', 'date'],
            'usage_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_user_limit' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $this->assertDiscount($data['discount_type'], (int) $data['discount_value']);
        $this->assertDateOrder($data['starts_on'] ?? null, $data['ends_on'] ?? null);

        $data['is_active'] = $data['is_active'] ?? true;

        return $data;
    }

    private function assertDiscount(string $type, int $value): void
    {
        if ($type === 'percent' && $value > 100) {
            throw ValidationException::withMessages([
                'discount_value' => ['Percent discount cannot be more than 100.'],
            ]);
        }
    }

    private function assertDateOrder(mixed $startsOn, mixed $endsOn): void
    {
        if ($startsOn === null || $startsOn === '' || $endsOn === null || $endsOn === '') {
            return;
        }

        $start = Carbon::parse((string) $startsOn, 'Asia/Kolkata')->startOfDay();
        $end = Carbon::parse((string) $endsOn, 'Asia/Kolkata')->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'ends_on' => ['The end date must be on or after the start date.'],
            ]);
        }
    }
}
