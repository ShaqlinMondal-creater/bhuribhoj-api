<?php

namespace App\Http\Controllers;

use App\Models\PaymentLogModel;
use App\Models\PlanModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function plansFetch(): JsonResponse
    {
        $plans = PlanModel::query()
            ->orderBy('duration_months')
            ->orderBy('id')
            ->get(['id', 'name', 'slug', 'duration_months', 'price_inr', 'is_active']);

        return response()->json([
            'data' => $plans,
        ]);
    }

    public function plansCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'price_inr' => ['required', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = PlanModel::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'duration_months' => $data['duration_months'],
            'price_inr' => $data['price_inr'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Plan created.',
            'data' => $plan,
        ], 201);
    }

    public function plansUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'string', 'max:120'],
            'duration_months' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'price_inr' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $plan = PlanModel::query()->find($data['id']);

        if (! $plan) {
            throw ValidationException::withMessages([
                'id' => ['Plan was not found.'],
            ]);
        }

        $changes = array_intersect_key($data, array_flip(['name', 'duration_months', 'price_inr', 'is_active']));

        if ($changes === []) {
            throw ValidationException::withMessages([
                'name' => ['No fields to update.'],
            ]);
        }

        if (array_key_exists('name', $changes)) {
            $changes['slug'] = $this->uniqueSlug($changes['name'], $plan->id);
        }

        $plan->fill($changes)->save();

        return response()->json([
            'message' => 'Plan updated.',
            'data' => $plan->fresh(),
        ]);
    }

    public function plansDelete(int $id): JsonResponse
    {
        $plan = PlanModel::query()->find($id);

        if (! $plan) {
            throw ValidationException::withMessages([
                'id' => ['Plan was not found.'],
            ]);
        }

        if ($plan->subscriptions()->exists() || PaymentLogModel::query()->where('plan_id', $plan->id)->exists()) {
            throw ValidationException::withMessages([
                'plan' => ['This plan already has a subscription or a payment, so it cannot be deleted.'],
            ]);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan deleted.',
        ]);
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'plan';
        }

        $slug = $base;
        $suffix = 2;

        while (
            PlanModel::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
