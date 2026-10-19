<?php

namespace App\Http\Controllers;

use App\Models\PlanModel;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    public function plansFetch(): JsonResponse
    {
        $plans = PlanModel::query()
            ->where('is_active', true)
            ->orderBy('duration_months')
            ->get(['id', 'name', 'slug', 'duration_months', 'price_inr']);

        return response()->json([
            'data' => $plans,
        ]);
    }
}
