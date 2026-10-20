<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_meal_setup', function (Blueprint $table) {
            $table->id();
            $table->string('meal_name');
            $table->decimal('price', 8, 2);
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['mess_id', 'meal_name']);
        });

        Schema::create('mess_p_m_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('president_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('to_date', 10)->nullable();
            $table->string('from_date', 10);
            $table->timestamps();

            $table->index(['mess_id', 'from_date']);
        });

        Schema::table('messes', function (Blueprint $table) {
            $table->string('monthly_due_date', 10)->nullable()->after('monthly_due_amount');
            $table->decimal('s_deposite_amount', 12, 2)->nullable()->after('monthly_due_date');
            $table->unsignedBigInteger('president_user_id')->nullable()->after('fund_mode');
            $table->unsignedBigInteger('manager_user_id')->nullable()->after('president_user_id');
            $table->unsignedBigInteger('guest_meal_setup_id')->nullable()->after('manager_user_id');
        });

        DB::statement('ALTER TABLE messes MODIFY email VARCHAR(255) NULL');
        DB::statement("ALTER TABLE messes CHANGE fund_mode fund_type ENUM('manager_only','mess_and_manager') NOT NULL DEFAULT 'manager_only'");

        $now = Carbon::now('Asia/Kolkata');

        foreach (DB::table('messes')->orderBy('id')->get() as $mess) {
            $mealIds = [];

            foreach ([
                'Chicken' => $mess->guest_chicken_price,
                'Fish' => $mess->guest_fish_price,
                'Egg' => $mess->guest_egg_price,
                'Veg' => $mess->guest_veg_price,
            ] as $mealName => $price) {
                $mealIds[] = DB::table('guest_meal_setup')->insertGetId([
                    'meal_name' => $mealName,
                    'price' => $price,
                    'mess_id' => $mess->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $presidentId = DB::table('users')
                ->where('mess_id', $mess->id)
                ->where('role', 'president')
                ->orderBy('id')
                ->value('id');

            $managerId = DB::table('mess_managers')
                ->where('mess_id', $mess->id)
                ->orderByDesc('id')
                ->value('user_id');

            $dueDate = null;
            $dueDay = (int) $mess->monthly_due_day;

            if ($dueDay >= 1 && $dueDay <= 31) {
                $anchor = Carbon::parse($mess->created_at)->timezone('Asia/Kolkata');
                $dueDay = min($dueDay, $anchor->daysInMonth);
                $dueDate = $anchor->copy()->day($dueDay)->format('d-m-Y');
            }

            DB::table('messes')->where('id', $mess->id)->update([
                'president_user_id' => $presidentId,
                'manager_user_id' => $managerId,
                'guest_meal_setup_id' => $mealIds[0] ?? null,
                'monthly_due_date' => $dueDate,
            ]);

            $assignments = DB::table('mess_managers')->where('mess_id', $mess->id)->orderBy('id')->get();

            if ($assignments->isEmpty() && $presidentId) {
                DB::table('mess_p_m_details')->insert([
                    'mess_id' => $mess->id,
                    'president_user_id' => $presidentId,
                    'manager_user_id' => null,
                    'from_date' => Carbon::parse($mess->created_at)->timezone('Asia/Kolkata')->format('d-m-Y'),
                    'to_date' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($assignments as $assignment) {
                $start = Carbon::createFromFormat('Y-m', $assignment->month, 'Asia/Kolkata')->startOfMonth();

                DB::table('mess_p_m_details')->insert([
                    'mess_id' => $mess->id,
                    'president_user_id' => $presidentId,
                    'manager_user_id' => $assignment->user_id,
                    'from_date' => $start->format('d-m-Y'),
                    'to_date' => $start->copy()->endOfMonth()->format('d-m-Y'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Schema::table('messes', function (Blueprint $table) {
            $table->foreign('president_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('manager_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('guest_meal_setup_id')->references('id')->on('guest_meal_setup')->nullOnDelete();
            $table->dropColumn([
                'guest_chicken_price',
                'guest_fish_price',
                'guest_egg_price',
                'guest_veg_price',
                'monthly_due_day',
            ]);
        });

        Schema::table('deposits', function (Blueprint $table) {
            $table->dropIndex(['mess_id', 'fund_type', 'deposited_on']);
            $table->string('deposited_on_text', 10)->nullable()->after('amount');
        });

        DB::statement("UPDATE deposits SET deposited_on_text = DATE_FORMAT(deposited_on, '%d-%m-%Y')");

        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn('deposited_on');
        });

        DB::statement('ALTER TABLE deposits CHANGE deposited_on_text deposited_on VARCHAR(10) NOT NULL');
        DB::statement('ALTER TABLE deposits CHANGE recorded_by added_by BIGINT UNSIGNED NULL');

        DB::statement('ALTER TABLE deposits MODIFY deposited_on VARCHAR(10) NOT NULL');
        DB::statement('ALTER TABLE deposits MODIFY for_month VARCHAR(10) NULL');
        DB::statement("UPDATE deposits SET for_month = DATE_FORMAT(STR_TO_DATE(CONCAT(for_month, '-01'), '%Y-%m-%d'), '%d-%m-%Y') WHERE for_month REGEXP '^[0-9]{4}-[0-9]{2}$'");

        Schema::table('deposits', function (Blueprint $table) {
            $table->index(['mess_id', 'fund_type', 'deposited_on']);
            $table->foreign('added_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropForeign(['added_by']);
            $table->dropIndex(['mess_id', 'fund_type', 'deposited_on']);
        });

        DB::statement('ALTER TABLE deposits ADD deposited_on_date DATE NULL');
        DB::statement("UPDATE deposits SET deposited_on_date = STR_TO_DATE(deposited_on, '%d-%m-%Y')");
        DB::statement('ALTER TABLE deposits DROP COLUMN deposited_on');
        DB::statement('ALTER TABLE deposits CHANGE deposited_on_date deposited_on DATE NOT NULL');
        DB::statement("UPDATE deposits SET for_month = DATE_FORMAT(STR_TO_DATE(for_month, '%d-%m-%Y'), '%Y-%m') WHERE for_month REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]{4}$'");
        DB::statement('ALTER TABLE deposits MODIFY for_month CHAR(7) NULL');
        DB::statement('ALTER TABLE deposits CHANGE added_by recorded_by BIGINT UNSIGNED NULL');

        Schema::table('deposits', function (Blueprint $table) {
            $table->index(['mess_id', 'fund_type', 'deposited_on']);
        });

        Schema::table('messes', function (Blueprint $table) {
            $table->dropForeign(['president_user_id']);
            $table->dropForeign(['manager_user_id']);
            $table->dropForeign(['guest_meal_setup_id']);
        });

        Schema::dropIfExists('mess_p_m_details');
        Schema::dropIfExists('guest_meal_setup');

        Schema::table('messes', function (Blueprint $table) {
            $table->dropColumn([
                'president_user_id',
                'manager_user_id',
                'guest_meal_setup_id',
                'monthly_due_date',
                's_deposite_amount',
            ]);
            $table->decimal('guest_chicken_price', 8, 2)->default(90);
            $table->decimal('guest_fish_price', 8, 2)->default(70);
            $table->decimal('guest_egg_price', 8, 2)->default(50);
            $table->decimal('guest_veg_price', 8, 2)->default(45);
            $table->unsignedTinyInteger('monthly_due_day')->default(5);
        });

        DB::statement("ALTER TABLE messes CHANGE fund_type fund_mode ENUM('manager_only','mess_and_manager') NOT NULL DEFAULT 'manager_only'");
        DB::statement('ALTER TABLE messes MODIFY email VARCHAR(255) NOT NULL');
    }
};
