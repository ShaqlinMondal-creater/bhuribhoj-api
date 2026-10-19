<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('meal_date');
            $table->boolean('lunch_active')->default(false);
            $table->boolean('lunch_taken')->default(false);
            $table->boolean('dinner_active')->default(false);
            $table->boolean('dinner_taken')->default(false);
            $table->unsignedBigInteger('marked_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['mess_id', 'user_id', 'meal_date']);
        });

        Schema::create('daily_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->date('menu_date');
            $table->enum('meal_type', ['lunch', 'dinner']);
            $table->text('dishes');
            $table->unsignedBigInteger('decided_by')->nullable()->index();
            $table->timestamps();

            $table->unique(['mess_id', 'menu_date', 'meal_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_menus');
        Schema::dropIfExists('daily_meals');
    }
};
