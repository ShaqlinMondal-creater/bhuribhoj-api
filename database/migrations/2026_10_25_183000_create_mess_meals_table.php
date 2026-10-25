<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mess_meals')) {
            Schema::create('mess_meals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
                $table->enum('meals_type', ['breakfast', 'morning_snack', 'lunch', 'evening_snack', 'dinner', 'late_night']);
                $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();

                $table->unique(['mess_id', 'meals_type']);
            });
        }

        $now = now();

        foreach (DB::table('messes')->get(['id', 'president_user_id']) as $mess) {
            $addedBy = $mess->president_user_id && DB::table('users')->where('id', $mess->president_user_id)->exists()
                ? $mess->president_user_id
                : null;

            foreach (['breakfast' => 'inactive', 'morning_snack' => 'inactive', 'lunch' => 'active', 'evening_snack' => 'inactive', 'dinner' => 'active', 'late_night' => 'inactive'] as $type => $status) {
                $exists = DB::table('mess_meals')
                    ->where('mess_id', $mess->id)
                    ->where('meals_type', $type)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('mess_meals')->insert([
                    'mess_id' => $mess->id,
                    'meals_type' => $type,
                    'added_by' => $addedBy,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('daily_meals') && ! Schema::hasColumn('daily_meals', 'breakfast_active')) {
            Schema::table('daily_meals', function (Blueprint $table) {
                $table->boolean('breakfast_active')->default(false)->after('meal_date');
                $table->boolean('breakfast_taken')->default(false)->after('breakfast_active');
            });
        }

        if (Schema::hasTable('meal_aways')) {
            DB::statement("ALTER TABLE meal_aways MODIFY from_meal ENUM('breakfast','morning_snack','lunch','evening_snack','dinner','late_night') NOT NULL");
            DB::statement("ALTER TABLE meal_aways MODIFY to_meal ENUM('breakfast','morning_snack','lunch','evening_snack','dinner','late_night') NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_meals');

        if (Schema::hasColumn('daily_meals', 'breakfast_active')) {
            Schema::table('daily_meals', function (Blueprint $table) {
                $table->dropColumn(['breakfast_active', 'breakfast_taken']);
            });
        }

        if (! Schema::hasTable('meal_aways')) {
            return;
        }

        DB::table('meal_aways')->where('from_meal', 'breakfast')->update(['from_meal' => 'lunch']);
        DB::table('meal_aways')->where('to_meal', 'breakfast')->update(['to_meal' => 'lunch']);
        DB::statement("ALTER TABLE meal_aways MODIFY from_meal ENUM('lunch','dinner') NOT NULL");
        DB::statement("ALTER TABLE meal_aways MODIFY to_meal ENUM('lunch','dinner') NULL");
    }
};
