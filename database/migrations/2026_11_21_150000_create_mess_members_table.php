<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mess_members')) {
            Schema::create('mess_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('president')->default(false);
                $table->boolean('manager')->default(false);
                $table->boolean('member')->default(true);
                $table->timestamps();

                $table->unique(['mess_id', 'user_id']);
                $table->index(['mess_id', 'president']);
                $table->index(['mess_id', 'manager']);
            });
        }

        $this->backfillExisting();
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_members');
    }

    private function backfillExisting(): void
    {
        if (! Schema::hasTable('mess_members') || ! Schema::hasTable('users')) {
            return;
        }

        $now = now();

        // Every mess user gets a seat row.
        $users = DB::table('users')
            ->whereNotNull('mess_id')
            ->whereIn('role', ['president', 'manager', 'member'])
            ->get(['id', 'mess_id', 'role']);

        foreach ($users as $user) {
            $exists = DB::table('mess_members')
                ->where('mess_id', $user->mess_id)
                ->where('user_id', $user->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('mess_members')->insert([
                'mess_id' => $user->mess_id,
                'user_id' => $user->id,
                'president' => $user->role === 'president',
                'manager' => $user->role === 'manager',
                'member' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Align with mess seat pointers when present.
        if (Schema::hasTable('messes')) {
            $messes = DB::table('messes')->get(['id', 'president_user_id', 'manager_user_id']);
            foreach ($messes as $mess) {
                if ($mess->president_user_id) {
                    DB::table('mess_members')
                        ->where('mess_id', $mess->id)
                        ->where('user_id', $mess->president_user_id)
                        ->update([
                            'president' => true,
                            'member' => true,
                            'updated_at' => $now,
                        ]);

                    // Ensure only one president flag in this mess.
                    DB::table('mess_members')
                        ->where('mess_id', $mess->id)
                        ->where('user_id', '!=', $mess->president_user_id)
                        ->where('president', true)
                        ->update([
                            'president' => false,
                            'updated_at' => $now,
                        ]);
                }

                if ($mess->manager_user_id) {
                    DB::table('mess_members')
                        ->where('mess_id', $mess->id)
                        ->where('user_id', $mess->manager_user_id)
                        ->update([
                            'manager' => true,
                            'member' => true,
                            'updated_at' => $now,
                        ]);
                }
            }
        }

        // Guarantee at most one president per mess (legacy data could be inconsistent).
        $messIds = DB::table('mess_members')->distinct()->pluck('mess_id');
        foreach ($messIds as $messId) {
            $presidentIds = DB::table('mess_members')
                ->where('mess_id', $messId)
                ->where('president', true)
                ->orderBy('id')
                ->pluck('user_id');

            if ($presidentIds->count() <= 1) {
                continue;
            }

            $keepId = $presidentIds->first();
            $messPointer = Schema::hasTable('messes')
                ? DB::table('messes')->where('id', $messId)->value('president_user_id')
                : null;
            if ($messPointer && $presidentIds->contains((int) $messPointer)) {
                $keepId = (int) $messPointer;
            }

            DB::table('mess_members')
                ->where('mess_id', $messId)
                ->where('user_id', '!=', $keepId)
                ->where('president', true)
                ->update([
                    'president' => false,
                    'updated_at' => $now,
                ]);
        }

        // Align mess pointer columns with seat truth.
        if (Schema::hasTable('messes')) {
            $messIds = DB::table('messes')->pluck('id');
            foreach ($messIds as $messId) {
                $presidentId = DB::table('mess_members')
                    ->where('mess_id', $messId)
                    ->where('president', true)
                    ->value('user_id');
                $managerId = DB::table('mess_members')
                    ->where('mess_id', $messId)
                    ->where('manager', true)
                    ->orderBy('id')
                    ->value('user_id');

                DB::table('messes')->where('id', $messId)->update([
                    'president_user_id' => $presidentId ?: null,
                    'manager_user_id' => $managerId ?: null,
                    'updated_at' => $now,
                ]);
            }
        }

        // Re-sync users.role from seats (president > manager > member).
        $seats = DB::table('mess_members')->get(['user_id', 'president', 'manager']);
        foreach ($seats as $seat) {
            $role = $seat->president ? 'president' : ($seat->manager ? 'manager' : 'member');
            DB::table('users')
                ->where('id', $seat->user_id)
                ->whereIn('role', ['president', 'manager', 'member'])
                ->update(['role' => $role, 'updated_at' => $now]);
        }
    }
};
