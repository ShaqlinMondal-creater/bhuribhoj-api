<?php

namespace App\Http\Controllers;

use App\Models\UserModel;
use App\Support\AccountSeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function authLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        $user = UserModel::query()
            ->with('mess:id,name,code')
            ->where('email', $credentials['email'])
            ->first();

        if ($user && ! $user->password && $user->google_id) {
            throw ValidationException::withMessages([
                'email' => ['This account signs in with Google.'],
            ]);
        }

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $user->forceFill(['is_loggedin' => true])->save();

        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
            'seat' => AccountSeat::for($user),
        ]);
    }

    public function authMe(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'seat' => AccountSeat::for($user),
        ]);
    }

    public function authProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar' => ['nullable', 'string', 'max:700000', 'regex:/^data:image\/(jpeg|png|webp);base64,/'],
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->fill(collect($data)->except(['password', 'password_confirmation'])->all());
        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $this->userPayload($user->fresh()),
        ]);
    }

    public function authLogout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['is_loggedin' => false])->save();
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out.',
        ]);
    }

    private function userPayload(UserModel $user): array
    {
        $user->loadMissing('mess:id,name,code');
        $payload = $user->toArray();
        $payload['avatar'] = $user->avatar;

        return $payload;
    }
}
