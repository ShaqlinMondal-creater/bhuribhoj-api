<?php

namespace App\Http\Controllers\Concerns;

use App\Models\UserModel;
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
}
