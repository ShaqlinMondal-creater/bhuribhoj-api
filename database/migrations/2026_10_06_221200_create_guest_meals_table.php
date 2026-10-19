<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_meals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->enum('guest_type', ['personal', 'guest'])->default('guest');
            $table->enum('dish_type', ['chicken', 'fish', 'egg', 'veg']);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 8, 2);
            $table->decimal('total_amount', 12, 2);
            $table->date('meal_date');
            $table->enum('meal_type', ['lunch', 'dinner'])->nullable();
            $table->string('guest_name')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['mess_id', 'meal_date']);
            $table->index(['member_id', 'meal_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_meals');
    }
};
