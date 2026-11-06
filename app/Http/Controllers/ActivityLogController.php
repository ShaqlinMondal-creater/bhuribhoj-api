<?php

namespace App\Http\Controllers;

use App\Models\ActivityLogModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActivityLogController extends Controller
{
    public function logsFetch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'action' => ['sometimes', 'nullable', 'string', 'max:80'],
            'from_date' => ['sometimes', 'nullable', 'date'],
            'to_date' => ['sometimes', 'nullable', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = ActivityLogModel::query()->with([
            'actor:id,name,email',
            'mess:id,name,code',
        ]);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('message', 'like', $like)
                    ->orWhere('action', 'like', $like)
                    ->orWhere('subject_type', 'like', $like)
                    ->orWhereHas('actor', function ($actor) use ($like) {
                        $actor->where('name', 'like', $like)->orWhere('email', 'like', $like);
                    });
            });
        }

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (! empty($filters['from_date'])) {
            $from = Carbon::parse($filters['from_date'], 'Asia/Kolkata')->startOfDay();
            $query->where('created_at', '>=', $from->format('Y-m-d H:i:s'));
        }

        if (! empty($filters['to_date'])) {
            $to = Carbon::parse($filters['to_date'], 'Asia/Kolkata')->endOfDay();
            if (! empty($filters['from_date'])) {
                $from = Carbon::parse($filters['from_date'], 'Asia/Kolkata')->startOfDay();
                if ($to->lt($from)) {
                    throw ValidationException::withMessages([
                        'to_date' => ['The end date must be on or after the start date.'],
                    ]);
                }
            }
            $query->where('created_at', '<=', $to->format('Y-m-d H:i:s'));
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
}
