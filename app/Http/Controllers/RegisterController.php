<?php

namespace App\Http\Controllers;

use App\Models\UserModel;
use App\Support\AccountSeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function registerEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = Str::lower($data['email']);
        $existing = UserModel::query()->where('email', $email)->first();

        if ($existing && ($existing->email_verified_at || $existing->password || $existing->google_id)) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Sign in instead.'],
            ]);
        }

        $token = Str::random(48);
        $local = Str::before($email, '@');
        $name = $local !== '' ? Str::headline(str_replace(['.', '_', '-'], ' ', $local)) : 'President';

        UserModel::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $existing?->name ?? $name,
                'role' => 'president',
                'status' => 'inactive',
                'otp' => hash('sha256', $token),
                'otp_expire_at' => now()->addDay(),
            ],
        );

        $verifyUrl = rtrim((string) config('services.frontend_url'), '/').'/register/verify?token='.$token;

        Mail::send('mail.verify-registration', [
            'verifyUrl' => $verifyUrl,
        ], function ($message) use ($email) {
            $message->to($email)->subject('Verify your BhuriBhoj email');
        });

        return response()->json([
            'message' => 'Verification email sent.',
        ]);
    }

    public function registerPreview(Request $request): JsonResponse
    {
        $registration = $this->registration($request);

        return response()->json([
            'email' => $registration->email,
            'expires_at' => $registration->otp_expire_at,
        ]);
    }

    public function registerComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'mobile' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        $user = $this->registration($request);
        $user->forceFill([
            'mobile' => $data['mobile'],
            'password' => $data['password'],
            'status' => 'active',
            'email_verified_at' => now(),
            'otp' => null,
            'otp_expire_at' => null,
        ])->save();

        Mail::send('mail.registration-done', [
            'name' => $user->name,
            'panelUrl' => rtrim((string) config('services.frontend_url'), '/').'/mess',
        ], function ($message) use ($user) {
            $message->to($user->email)->subject('Your BhuriBhoj registration is done');
        });

        return $this->session($user, $data['device_name'] ?? 'api', 'Registration complete.', 201);
    }

    public function google(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:20'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        $clientId = (string) config('services.google.client_id');
        if ($clientId === '') {
            return response()->json([
                'message' => 'Google sign-in is not configured.',
            ], 503);
        }

        $google = Http::acceptJson()
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $data['id_token'],
            ]);

        if (! $google->successful()) {
            throw ValidationException::withMessages([
                'id_token' => ['Google could not verify this sign-in.'],
            ]);
        }

        $payload = $google->json();

        $emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (($payload['aud'] ?? '') !== $clientId || ! $emailVerified || empty($payload['email'])) {
            throw ValidationException::withMessages([
                'id_token' => ['Google could not verify this sign-in.'],
            ]);
        }

        $email = Str::lower($payload['email']);
        $user = UserModel::query()->where('google_id', $payload['sub'] ?? null)->first()
            ?? UserModel::query()->where('email', $email)->first();

        if (! $user && empty($data['mobile'])) {
            return response()->json([
                'message' => 'Mobile is required to finish Google sign-up.',
                'needs_mobile' => true,
                'email' => $email,
                'name' => $payload['name'] ?? $email,
            ], 422);
        }

        if (! $user) {
            $user = UserModel::query()->create([
                'name' => $payload['name'] ?? Str::before($email, '@'),
                'email' => $email,
                'google_id' => $payload['sub'] ?? null,
                'mobile' => $data['mobile'],
                'role' => 'president',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            Mail::send('mail.registration-done', [
                'name' => $user->name,
                'panelUrl' => rtrim((string) config('services.frontend_url'), '/').'/mess',
            ], function ($message) use ($user) {
                $message->to($user->email)->subject('Your BhuriBhoj registration is done');
            });
        } else {
            if ($user->status !== 'active' && ($user->email_verified_at || $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['This account is inactive.'],
                ]);
            }

            $user->forceFill([
                'google_id' => $user->google_id ?: ($payload['sub'] ?? null),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'mobile' => $user->mobile ?: ($data['mobile'] ?? null),
                'status' => 'active',
                'otp' => null,
                'otp_expire_at' => null,
            ])->save();
        }

        return $this->session($user, $data['device_name'] ?? 'google', 'Signed in.');
    }

    private function registration(Request $request): UserModel
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = UserModel::query()
            ->where('otp', hash('sha256', $data['token']))
            ->whereNull('email_verified_at')
            ->first();

        if (! $user || ! $user->otp_expire_at || $user->otp_expire_at->isPast()) {
            throw ValidationException::withMessages([
                'token' => ['This verification link is invalid or expired.'],
            ]);
        }

        return $user;
    }

    private function session(UserModel $user, string $device, string $message, int $status = 200): JsonResponse
    {
        $user->forceFill(['is_loggedin' => true])->save();

        return response()->json([
            'message' => $message,
            'token' => $user->createToken($device)->plainTextToken,
            'token_type' => 'Bearer',
            'user' => $user->fresh()->load('mess:id,name,code'),
            'seat' => AccountSeat::for($user->fresh()),
        ], $status);
    }
}
