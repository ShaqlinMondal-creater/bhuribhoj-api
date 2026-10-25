<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesMessId;
use App\Models\GuestMealSetupModel;
use App\Models\MessMealModel;
use App\Models\MessModel;
use App\Models\MessPmDetailModel;
use App\Models\MessSubscriptionModel;
use App\Models\UserModel;
use App\Rules\KolkataDateRule;
use App\Support\AccountSeat;
use App\Support\MemberCodeGenerator;
use App\Support\MessCodeGenerator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MessController extends Controller
{
    use ResolvesMessId;

    public function messesFetch(Request $request): JsonResponse
    {
        $actor = $request->user();

        if ($actor->role === 'admin') {
            $messes = MessModel::query()->latest()->paginate(20);

            return response()->json($messes);
        }

        if ($blank = $this->blankMessResponse($actor, ['data' => null])) {
            return $blank;
        }

        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()
            ->with([
                'activeSubscription.plan',
                'presidentUser:id,name,email,role',
                'managerUser:id,name,email,role',
                'guestMealSetups:id,mess_id,meal_name,price',
            ])
            ->findOrFail($messId);

        return response()->json([
            'data' => $mess,
        ]);
    }

    public function messesOpen(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! in_array($actor->role, ['admin', 'president'], true) || ($actor->role === 'president' && $actor->mess_id)) {
            throw ValidationException::withMessages([
                'mess' => ['This account cannot open a mess.'],
            ]);
        }

        $subscription = null;

        if ($actor->role === 'president') {
            $subscription = MessSubscriptionModel::query()
                ->where('purchased_by', $actor->id)
                ->whereNull('mess_id')
                ->where('status', 'active')
                ->whereDate('ends_on', '>=', now()->toDateString())
                ->latest('id')
                ->first();

            if (! $subscription) {
                throw ValidationException::withMessages([
                    'mess' => ['Buy a subscription before saving the mess details.'],
                ]);
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'unique:messes,code'],
            'address' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:120'],
            'pincode' => ['required', 'string', 'max:12'],
            'contact_no' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'fund_type' => ['sometimes', Rule::in(['manager_only', 'mess_and_manager'])],
            'monthly_due_amount' => ['sometimes', 'numeric', 'min:0'],
            'monthly_due_days' => ['nullable', 'integer', 'min:1', 'max:31'],
            's_deposite_amount' => ['nullable', 'numeric', 'min:0'],
            'late_fee_amount' => ['sometimes', 'numeric', 'min:0'],
            'president_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        $fundType = $data['fund_type'] ?? 'manager_only';
        $this->assertDepositAmount($fundType, $data['s_deposite_amount'] ?? null);

        $presidentId = $actor->role === 'president'
            ? (int) $actor->id
            : (isset($data['president_user_id']) ? (int) $data['president_user_id'] : null);

        if ($presidentId) {
            $this->assertCanLeadMess($presidentId, null);
        }

        $mess = DB::transaction(function () use ($actor, $subscription, $data, $fundType, $presidentId) {
            $mess = MessModel::query()->create([
                'name' => $data['name'],
                'code' => $data['code'] ?? MessCodeGenerator::fromName($data['name']),
                'address' => $data['address'],
                'district' => $data['district'],
                'pincode' => $data['pincode'],
                'contact_no' => $data['contact_no'],
                'email' => $data['email'] ?? null,
                'fund_type' => $fundType,
                'monthly_due_amount' => $data['monthly_due_amount'] ?? 0,
                'monthly_due_days' => $data['monthly_due_days'] ?? null,
                's_deposite_amount' => $fundType === 'mess_and_manager' ? ($data['s_deposite_amount'] ?? null) : null,
                'late_fee_amount' => $data['late_fee_amount'] ?? 0,
                'president_user_id' => $presidentId,
                'is_active' => true,
            ]);

            $firstMealId = null;

            foreach (['Chicken' => 90, 'Fish' => 70, 'Egg' => 50, 'Veg' => 45] as $mealName => $price) {
                $meal = GuestMealSetupModel::query()->create([
                    'mess_id' => $mess->id,
                    'meal_name' => $mealName,
                    'price' => $price,
                ]);
                $firstMealId ??= $meal->id;
            }

            $mess->guest_meal_setup_id = $firstMealId;
            $mess->save();

            if ($presidentId) {
                $president = UserModel::query()->findOrFail($presidentId);
                $president->forceFill([
                    'mess_id' => $mess->id,
                    'role' => 'president',
                    'member_code' => $president->member_code ?: MemberCodeGenerator::next($mess),
                    'joining_date' => $president->joining_date ?? now(),
                ])->save();

                MessPmDetailModel::query()->create([
                    'mess_id' => $mess->id,
                    'president_user_id' => $presidentId,
                    'manager_user_id' => null,
                    'from_date' => now()->timezone('Asia/Kolkata')->format('d-m-Y'),
                    'to_date' => null,
                ]);
            }

            if ($subscription) {
                $subscription->forceFill(['mess_id' => $mess->id])->save();
            }

            MessMealModel::ensure($mess->id);

            if ($presidentId) {
                MessMealModel::query()->where('mess_id', $mess->id)->update(['added_by' => $presidentId]);
            }

            return $mess;
        });

        return response()->json([
            'message' => 'Mess created.',
            'data' => $mess->fresh()->load(['activeSubscription.plan', 'guestMealSetups', 'presidentUser:id,name,email,role']),
            'seat' => AccountSeat::for($actor->fresh()),
        ], 201);
    }

    public function messesUpdate(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);
        $mess = MessModel::query()->findOrFail($messId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'district' => ['sometimes', 'string', 'max:120'],
            'pincode' => ['sometimes', 'string', 'max:12'],
            'contact_no' => ['sometimes', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'fund_type' => ['sometimes', Rule::in(['manager_only', 'mess_and_manager'])],
            'monthly_due_amount' => ['sometimes', 'numeric', 'min:0'],
            'monthly_due_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],
            's_deposite_amount' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'late_fee_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'mess' => ['No fields to update.'],
            ]);
        }

        $fundType = $data['fund_type'] ?? $mess->fund_type;
        $depositAmount = array_key_exists('s_deposite_amount', $data)
            ? $data['s_deposite_amount']
            : $mess->s_deposite_amount;
        $this->assertDepositAmount($fundType, $depositAmount);

        if ($fundType === 'manager_only') {
            $data['s_deposite_amount'] = null;
        }

        $mess->fill($data)->save();

        return response()->json([
            'message' => 'Mess updated.',
            'data' => $mess->fresh(),
        ]);
    }

    public function messesDelete(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        if ((int) $id !== $messId) {
            throw ValidationException::withMessages([
                'mess_id' => ['This mess does not match the record.'],
            ]);
        }

        $mess = MessModel::query()->findOrFail($messId);

        if ($mess->users()->exists() || $mess->deposits()->exists() || $mess->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'mess' => ['Remove the people, deposits, and subscription before deleting this mess.'],
            ]);
        }

        $mess->delete();

        return response()->json([
            'message' => 'Mess deleted.',
        ]);
    }

    public function messesAssign(Request $request): JsonResponse
    {
        $actor = $request->user();
        $messId = $this->resolveMessId($actor, $request);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'seat' => ['required', Rule::in(['president', 'manager'])],
            'from_date' => ['required', new KolkataDateRule],
            'to_date' => ['nullable', new KolkataDateRule],
        ]);

        if (! empty($data['to_date'])) {
            $from = Carbon::createFromFormat('d-m-Y', $data['from_date'], 'Asia/Kolkata')->startOfDay();
            $to = Carbon::createFromFormat('d-m-Y', $data['to_date'], 'Asia/Kolkata')->startOfDay();

            if ($to->lt($from)) {
                throw ValidationException::withMessages([
                    'to_date' => ['The end date must be on or after the start date.'],
                ]);
            }
        }

        $user = UserModel::query()
            ->where('id', $data['user_id'])
            ->where('mess_id', $messId)
            ->first();

        if (! $user || $user->role === 'admin') {
            throw ValidationException::withMessages([
                'user_id' => ['Choose a person who already belongs to this mess.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'user_id' => ['Only an active person can be assigned.'],
            ]);
        }

        $mess = DB::transaction(function () use ($messId, $user, $data) {
            $mess = MessModel::query()->lockForUpdate()->findOrFail($messId);

            if ($data['seat'] === 'president') {
                if ($mess->president_user_id && (int) $mess->president_user_id !== (int) $user->id) {
                    UserModel::query()
                        ->where('id', $mess->president_user_id)
                        ->where('role', 'president')
                        ->update(['role' => 'member']);
                }

                if ((int) $mess->manager_user_id === (int) $user->id) {
                    $mess->manager_user_id = null;
                }

                $user->role = 'president';
                $mess->president_user_id = $user->id;
            } else {
                if ($user->role === 'president' || (int) $mess->president_user_id === (int) $user->id) {
                    throw ValidationException::withMessages([
                        'user_id' => ['The president cannot also be assigned as the manager.'],
                    ]);
                }

                if ($mess->manager_user_id && (int) $mess->manager_user_id !== (int) $user->id) {
                    UserModel::query()
                        ->where('id', $mess->manager_user_id)
                        ->where('role', 'manager')
                        ->update(['role' => 'member']);
                }

                $user->role = 'manager';
                $mess->manager_user_id = $user->id;
            }

            $user->save();
            $mess->save();

            $open = MessPmDetailModel::query()
                ->where('mess_id', $messId)
                ->whereNull('to_date')
                ->latest('id')
                ->first();

            if ($open) {
                $from = Carbon::createFromFormat('d-m-Y', $data['from_date'], 'Asia/Kolkata')->startOfDay();
                $openFrom = Carbon::createFromFormat('d-m-Y', (string) $open->from_date, 'Asia/Kolkata')->startOfDay();
                $end = $from->copy()->subDay();

                if ($end->lt($openFrom)) {
                    $end = $openFrom;
                }

                $open->to_date = $end->format('d-m-Y');
                $open->save();
            }

            MessPmDetailModel::query()->create([
                'mess_id' => $messId,
                'president_user_id' => $mess->president_user_id,
                'manager_user_id' => $mess->manager_user_id,
                'from_date' => $data['from_date'],
                'to_date' => $data['to_date'] ?? null,
            ]);

            return $mess->fresh()->load([
                'presidentUser:id,name,email,role',
                'managerUser:id,name,email,role',
            ]);
        });

        return response()->json([
            'message' => 'Seat assigned.',
            'data' => $mess,
        ]);
    }

    private function assertDepositAmount(string $fundType, mixed $amount): void
    {
        if ($fundType === 'manager_only' && $amount !== null && $amount !== '') {
            throw ValidationException::withMessages([
                's_deposite_amount' => ['A starting deposit is only used when the mess has both funds.'],
            ]);
        }
    }

    private function assertCanLeadMess(int $userId, ?int $messId): void
    {
        $user = UserModel::query()->find($userId);

        if (! $user || $user->role === 'admin' || ($user->mess_id && (int) $user->mess_id !== (int) $messId)) {
            throw ValidationException::withMessages([
                'president_user_id' => ['Choose a person who is not already in another mess.'],
            ]);
        }
    }
}
