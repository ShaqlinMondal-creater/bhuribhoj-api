<?php

namespace App\Http\Controllers;

use App\Models\PlatformConfigModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlatformConfigController extends Controller
{
    public function configsFetch(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $limit = (int) ($filters['limit'] ?? 50);
        $offset = (int) ($filters['offset'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = PlatformConfigModel::query()->with('updater:id,name');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($inner) use ($like) {
                $inner->where('config_key', 'like', $like)
                    ->orWhere('note', 'like', $like);
            });
        }

        $count = (clone $query)->count();
        $rows = $query
            ->orderBy('config_key')
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

    public function configsSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'config_key' => ['required', 'string', 'max:120'],
            'config_value' => ['sometimes', 'nullable', 'string'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $key = strtolower(trim($data['config_key']));

        if ($key === '' || ! preg_match('/^[a-z0-9._-]+$/', $key)) {
            throw ValidationException::withMessages([
                'config_key' => ['Use letters, numbers, dots, dashes, or underscores only.'],
            ]);
        }

        $row = PlatformConfigModel::query()->firstOrNew(['config_key' => $key]);
        $row->config_value = array_key_exists('config_value', $data) ? $data['config_value'] : $row->config_value;
        if (array_key_exists('note', $data)) {
            $row->note = $data['note'];
        }
        $row->updated_by = $request->user()->id;
        $row->save();

        return response()->json([
            'message' => 'Config saved.',
            'data' => $row->fresh()->load('updater:id,name'),
        ]);
    }

    public function configsDelete(int $id): JsonResponse
    {
        $row = PlatformConfigModel::query()->find($id);

        if (! $row) {
            throw ValidationException::withMessages([
                'id' => ['Config was not found.'],
            ]);
        }

        $row->delete();

        return response()->json([
            'message' => 'Config deleted.',
        ]);
    }
}
