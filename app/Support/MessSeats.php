<?php

namespace App\Support;

use App\Models\MessMemberModel;
use App\Models\MessModel;
use App\Models\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessSeats
{
    public const MAX_MANAGERS = 3;

    /**
     * @return array{president: bool, manager: bool, member: bool}
     */
    public static function flagsFor(int $messId, int $userId): array
    {
        $seat = MessMemberModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', $userId)
            ->first();

        if (! $seat) {
            return ['president' => false, 'manager' => false, 'member' => false];
        }

        return $seat->flags();
    }

    public static function ensure(int $messId, int $userId, bool $member = true): MessMemberModel
    {
        $seat = MessMemberModel::query()->firstOrCreate(
            ['mess_id' => $messId, 'user_id' => $userId],
            ['president' => false, 'manager' => false, 'member' => $member],
        );

        if ($member && ! $seat->member) {
            $seat->forceFill(['member' => true])->save();
        }

        return $seat->fresh();
    }

    /**
     * Apply seat flags for one user in a mess, enforce caps, sync role + mess pointers.
     *
     * @param  array{president?: bool, manager?: bool, member?: bool}  $flags
     */
    public static function apply(int $messId, int $userId, array $flags, ?int $actorId = null): MessMemberModel
    {
        return DB::transaction(function () use ($messId, $userId, $flags, $actorId) {
            $mess = MessModel::query()->lockForUpdate()->findOrFail($messId);
            $user = UserModel::query()
                ->where('id', $userId)
                ->where('mess_id', $messId)
                ->lockForUpdate()
                ->first();

            if (! $user || $user->role === 'admin') {
                throw ValidationException::withMessages([
                    'user_id' => ['Choose a person who already belongs to this mess.'],
                ]);
            }

            $seat = self::ensure($messId, $userId, true);
            $seat = MessMemberModel::query()->where('id', $seat->id)->lockForUpdate()->firstOrFail();

            $nextPresident = array_key_exists('president', $flags) ? (bool) $flags['president'] : (bool) $seat->president;
            $nextManager = array_key_exists('manager', $flags) ? (bool) $flags['manager'] : (bool) $seat->manager;
            $nextMember = array_key_exists('member', $flags) ? (bool) $flags['member'] : true;

            if (! $nextMember && ($nextPresident || $nextManager)) {
                $nextMember = true;
            }

            // Stepping down as the only president is blocked unless another president is already set
            // in the same transaction via transfer (handled by setPresident).
            if ($seat->president && ! $nextPresident) {
                $otherPresident = MessMemberModel::query()
                    ->where('mess_id', $messId)
                    ->where('user_id', '!=', $userId)
                    ->where('president', true)
                    ->exists();

                if (! $otherPresident) {
                    throw ValidationException::withMessages([
                        'president' => ['Assign another president before removing this president seat.'],
                    ]);
                }
            }

            if ($nextPresident && ! $seat->president) {
                self::clearPresidentFlag($messId, $userId);
            }

            if ($nextManager && ! $seat->manager) {
                $managerCount = MessMemberModel::query()
                    ->where('mess_id', $messId)
                    ->where('manager', true)
                    ->where('user_id', '!=', $userId)
                    ->count();

                if ($managerCount >= self::MAX_MANAGERS) {
                    throw ValidationException::withMessages([
                        'manager' => ['This mess already has '.self::MAX_MANAGERS.' managers.'],
                    ]);
                }
            }

            // Actor cannot leave mess with zero presidents by clearing self without transfer.
            if ($actorId && (int) $actorId === (int) $userId && $seat->president && ! $nextPresident) {
                // already validated other president exists above
            }

            $seat->forceFill([
                'president' => $nextPresident,
                'manager' => $nextManager,
                'member' => $nextMember,
            ])->save();

            self::syncUserRole($userId);
            self::syncMessPointers($mess);

            return $seat->fresh();
        });
    }

    public static function setPresident(int $messId, int $userId): MessMemberModel
    {
        return DB::transaction(function () use ($messId, $userId) {
            $mess = MessModel::query()->lockForUpdate()->findOrFail($messId);
            $user = UserModel::query()
                ->where('id', $userId)
                ->where('mess_id', $messId)
                ->lockForUpdate()
                ->first();

            if (! $user || $user->status !== 'active') {
                throw ValidationException::withMessages([
                    'user_id' => ['Only an active mess member can be president.'],
                ]);
            }

            self::clearPresidentFlag($messId, $userId);
            $seat = self::ensure($messId, $userId, true);
            $seat->forceFill([
                'president' => true,
                'member' => true,
            ])->save();

            // Previous presidents keep manager/member flags; only president flag cleared.
            self::resyncRolesForMess($messId);
            self::syncMessPointers($mess->fresh());

            return $seat->fresh();
        });
    }

    public static function setManager(int $messId, int $userId, bool $enabled): MessMemberModel
    {
        return self::apply($messId, $userId, [
            'manager' => $enabled,
            'member' => true,
        ]);
    }

    public static function removeUser(int $messId, int $userId): void
    {
        DB::transaction(function () use ($messId, $userId) {
            MessMemberModel::query()
                ->where('mess_id', $messId)
                ->where('user_id', $userId)
                ->delete();

            $mess = MessModel::query()->find($messId);
            if ($mess) {
                self::syncMessPointers($mess);
            }
        });
    }

    public static function doorRoleFromFlags(bool $president, bool $manager): string
    {
        if ($president) {
            return 'president';
        }
        if ($manager) {
            return 'manager';
        }

        return 'member';
    }

    public static function syncUserRole(int $userId): void
    {
        $user = UserModel::query()->find($userId);
        if (! $user || $user->role === 'admin' || ! $user->mess_id) {
            return;
        }

        $seat = MessMemberModel::query()
            ->where('mess_id', $user->mess_id)
            ->where('user_id', $userId)
            ->first();

        $role = $seat
            ? self::doorRoleFromFlags((bool) $seat->president, (bool) $seat->manager)
            : 'member';

        if ($user->role !== $role) {
            $user->forceFill(['role' => $role])->save();
        }
    }

    public static function syncMessPointers(MessModel $mess): void
    {
        $presidentId = MessMemberModel::query()
            ->where('mess_id', $mess->id)
            ->where('president', true)
            ->value('user_id');

        $managerId = MessMemberModel::query()
            ->where('mess_id', $mess->id)
            ->where('manager', true)
            ->orderBy('id')
            ->value('user_id');

        $mess->forceFill([
            'president_user_id' => $presidentId ?: null,
            'manager_user_id' => $managerId ?: null,
        ])->save();
    }

    public static function resyncRolesForMess(int $messId): void
    {
        $seats = MessMemberModel::query()->where('mess_id', $messId)->get(['user_id', 'president', 'manager']);
        foreach ($seats as $seat) {
            $role = self::doorRoleFromFlags((bool) $seat->president, (bool) $seat->manager);
            UserModel::query()
                ->where('id', $seat->user_id)
                ->whereIn('role', ['president', 'manager', 'member'])
                ->update(['role' => $role]);
        }
    }

    private static function clearPresidentFlag(int $messId, int $keepUserId): void
    {
        MessMemberModel::query()
            ->where('mess_id', $messId)
            ->where('user_id', '!=', $keepUserId)
            ->where('president', true)
            ->update(['president' => false]);
    }
}
