<?php

namespace App\Http\Controllers;

use App\Models\UserModel;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function usersFetch(): JsonResponse
    {
        $users = UserModel::query()
            ->with('mess:id,name')
            ->orderBy('role')
            ->orderBy('name')
            ->get(['id', 'mess_id', 'name', 'email', 'mobile', 'role', 'status', 'member_code']);

        return response()->json([
            'data' => $users->map(fn (UserModel $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role,
                'status' => $user->status,
                'member_code' => $user->member_code,
                'mess' => $user->mess ? [
                    'id' => $user->mess->id,
                    'name' => $user->mess->name,
                ] : null,
            ])->values(),
        ]);
    }
}
