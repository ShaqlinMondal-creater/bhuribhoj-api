<?php

namespace App\Http\Controllers\Concerns;

use App\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ResolvesMessId
{
    private function resolveMessId(UserModel $user, Request $request): int
    {
        if ($user->role === 'admin') {
            $data = $request->validate([
                'mess_id' => ['required', 'integer', Rule::exists('messes', 'id')],
            ]);

            return (int) $data['mess_id'];
        }

        if (! $user->mess_id) {
            throw ValidationException::withMessages([
                'mess' => ['Your account is not linked to a mess.'],
            ]);
        }

        return (int) $user->mess_id;
    }

    /**
     * A signed-in house account with no mess yet has nothing to list.
     * Callers return this empty payload instead of an error.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function blankMessResponse(UserModel $user, array $payload): ?JsonResponse
    {
        if ($user->role === 'admin' || $user->mess_id) {
            return null;
        }

        return response()->json($payload);
    }
}
