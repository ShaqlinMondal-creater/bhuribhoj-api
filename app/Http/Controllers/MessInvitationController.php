<?php

namespace App\Http\Controllers;

use App\Models\MessInvitationModel;
use App\Models\PlanModel;
use App\Models\UserModel;
use App\Services\MessOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessInvitationController extends Controller
{
    public function __construct(
        private readonly MessOnboardingService $onboarding,
    ) {}

    public function messInvitationsFetch(Request $request): JsonResponse
    {
        $invitations = MessInvitationModel::query()
            ->with(['plan:id,name,slug,price_inr,duration_months', 'invitedBy:id,name,email'])
            ->latest()
            ->paginate(20);

        return response()->json($invitations);
    }

    public function messInvitationsCreate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'email' => ['required', 'email', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'mess_name' => ['nullable', 'string', 'max:255'],
            'expires_in_days' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        $plan = PlanModel::query()->findOrFail($data['plan_id']);

        if (! $plan->is_active) {
            throw ValidationException::withMessages([
                'plan_id' => ['This plan is not available.'],
            ]);
        }

        if (UserModel::query()->where('email', $data['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        $pendingExists = MessInvitationModel::query()
            ->where('email', $data['email'])
            ->where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($pendingExists) {
            throw ValidationException::withMessages([
                'email' => ['A pending invitation already exists for this email.'],
            ]);
        }

        $invitation = MessInvitationModel::query()->create([
            'invited_by' => $request->user()->id,
            'plan_id' => $plan->id,
            'mess_name' => $data['mess_name'] ?? null,
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'token' => Str::random(48),
            'status' => 'pending',
            'expires_at' => now()->addDays($data['expires_in_days'] ?? 7),
        ]);

        return response()->json([
            'message' => 'Mess invitation created.',
            'data' => $invitation->load('plan:id,name,slug,price_inr,duration_months'),
        ], 201);
    }

    public function messInvitationsCancel(Request $request, int $id): JsonResponse
    {
        $invitation = MessInvitationModel::query()->findOrFail($id);

        if ($invitation->status !== 'pending') {
            throw ValidationException::withMessages([
                'invitation' => ['Only pending invitations can be cancelled.'],
            ]);
        }

        $invitation->forceFill(['status' => 'cancelled'])->save();

        return response()->json([
            'message' => 'Invitation cancelled.',
            'data' => $invitation,
        ]);
    }

    public function messInvitationsTokenFetch(string $token): JsonResponse
    {
        $invitation = $this->findUsableInvitation($token);

        return response()->json([
            'data' => $invitation->load('plan:id,name,slug,price_inr,duration_months'),
        ]);
    }

    public function messInvitationsTokenAccept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->findUsableInvitation($token);

        $data = $request->validate([
            'mess.name' => ['required', 'string', 'max:255'],
            'mess.code' => ['nullable', 'string', 'max:32', 'unique:messes,code'],
            'mess.address' => ['required', 'string', 'max:255'],
            'mess.district' => ['required', 'string', 'max:120'],
            'mess.pincode' => ['required', 'string', 'max:12'],
            'mess.contact_no' => ['required', 'string', 'max:20'],
            'mess.email' => ['required', 'email', 'max:255'],
            'mess.fund_mode' => ['sometimes', Rule::in(['manager_only', 'mess_and_manager'])],
            'president.name' => ['required', 'string', 'max:255'],
            'president.password' => ['required', 'string', 'min:8'],
            'president.mobile' => ['nullable', 'string', 'max:20'],
            'president.room_no' => ['nullable', 'string', 'max:32'],
            'device_name' => ['sometimes', 'string', 'max:120'],
        ]);

        $plan = PlanModel::query()->findOrFail($invitation->plan_id);

        $result = $this->onboarding->provision(
            plan: $plan,
            messData: $data['mess'],
            presidentData: [
                'name' => $data['president']['name'],
                'email' => $invitation->email,
                'password' => $data['president']['password'],
                'mobile' => $data['president']['mobile'] ?? $invitation->mobile,
                'room_no' => $data['president']['room_no'] ?? null,
            ],
            purchaseSource: 'admin_invite',
            purchasedBy: null,
            invitation: $invitation,
        );

        $tokenValue = $result['president']->createToken($data['device_name'] ?? 'api')->plainTextToken;
        $result['president']->forceFill(['is_loggedin' => true])->save();

        return response()->json([
            'message' => 'Invitation accepted. Mess created.',
            'token' => $tokenValue,
            'token_type' => 'Bearer',
            'mess' => $result['mess'],
            'user' => $result['president']->fresh()->load('mess:id,name,code'),
            'subscription' => $result['subscription'],
        ], 201);
    }

    private function findUsableInvitation(string $token): MessInvitationModel
    {
        $invitation = MessInvitationModel::query()
            ->where('token', $token)
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'token' => ['Invitation not found.'],
            ]);
        }

        if ($invitation->status !== 'pending') {
            throw ValidationException::withMessages([
                'token' => ['This invitation is no longer valid.'],
            ]);
        }

        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->forceFill(['status' => 'expired'])->save();

            throw ValidationException::withMessages([
                'token' => ['This invitation has expired.'],
            ]);
        }

        return $invitation;
    }
}
