<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ENUM = "ENUM('breakfast','morning_snack','lunch','evening_snack','dinner','late_night')";

    public function up(): void
    {
        if (Schema::hasTable('mess_meals')) {
            DB::statement('ALTER TABLE mess_meals MODIFY meals_type '.self::ENUM.' NOT NULL');

            $now = now();

            foreach (DB::table('messes')->pluck('id') as $messId) {
                foreach (['breakfast', 'morning_snack', 'lunch', 'evening_snack', 'dinner', 'late_night'] as $type) {
                    $exists = DB::table('mess_meals')
                        ->where('mess_id', $messId)
                        ->where('meals_type', $type)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('mess_meals')->insert([
                        'mess_id' => $messId,
                        'meals_type' => $type,
                        'added_by' => null,
                        'status' => in_array($type, ['lunch', 'dinner'], true) ? 'active' : 'inactive',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (Schema::hasTable('daily_meals')) {
            $missing = array_values(array_filter([
                'morning_snack_active',
                'morning_snack_taken',
                'evening_snack_active',
                'evening_snack_taken',
                'late_night_active',
                'late_night_taken',
            ], fn (string $column) => ! Schema::hasColumn('daily_meals', $column)));

            if ($missing !== []) {
                Schema::table('daily_meals', function (Blueprint $table) use ($missing) {
                    foreach ($missing as $column) {
                        $table->boolean($column)->default(false);
                    }
                });
            }
        }

        if (Schema::hasTable('meal_aways')) {
            DB::statement('ALTER TABLE meal_aways MODIFY from_meal '.self::ENUM.' NOT NULL');
            DB::statement('ALTER TABLE meal_aways MODIFY to_meal '.self::ENUM.' NULL');
        }

        if (Schema::hasTable('daily_menus') && Schema::hasColumn('daily_menus', 'meal_type')) {
            DB::statement('ALTER TABLE daily_menus MODIFY meal_type '.self::ENUM.' NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mess_meals')) {
            DB::table('mess_meals')->whereIn('meals_type', ['morning_snack', 'evening_snack', 'late_night'])->delete();
            DB::statement("ALTER TABLE mess_meals MODIFY meals_type ENUM('breakfast','lunch','dinner') NOT NULL");
        }

        if (Schema::hasTable('daily_meals')) {
            $drop = array_values(array_filter([
                'morning_snack_active',
                'morning_snack_taken',
                'evening_snack_active',
                'evening_snack_taken',
                'late_night_active',
                'late_night_taken',
            ], fn (string $column) => Schema::hasColumn('daily_meals', $column)));

            if ($drop !== []) {
                Schema::table('daily_meals', function (Blueprint $table) use ($drop) {
                    $table->dropColumn($drop);
                });
            }
        }

        if (Schema::hasTable('meal_aways')) {
            DB::table('meal_aways')->whereIn('from_meal', ['morning_snack', 'evening_snack', 'late_night'])->update(['from_meal' => 'lunch']);
            DB::table('meal_aways')->whereIn('to_meal', ['morning_snack', 'evening_snack', 'late_night'])->update(['to_meal' => 'lunch']);
            DB::statement("ALTER TABLE meal_aways MODIFY from_meal ENUM('breakfast','lunch','dinner') NOT NULL");
            DB::statement("ALTER TABLE meal_aways MODIFY to_meal ENUM('breakfast','lunch','dinner') NULL");
        }

        if (Schema::hasTable('daily_menus') && Schema::hasColumn('daily_menus', 'meal_type')) {
            DB::table('daily_menus')->whereIn('meal_type', ['breakfast', 'morning_snack', 'evening_snack', 'late_night'])->update(['meal_type' => 'lunch']);
            DB::statement("ALTER TABLE daily_menus MODIFY meal_type ENUM('lunch','dinner') NOT NULL");
        }
    }
};
