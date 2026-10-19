<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('address');
            $table->string('district');
            $table->string('pincode', 12);
            $table->string('contact_no', 20);
            $table->string('email');
            $table->enum('fund_mode', ['manager_only', 'mess_and_manager'])->default('manager_only');
            $table->decimal('guest_chicken_price', 8, 2)->default(90);
            $table->decimal('guest_fish_price', 8, 2)->default(70);
            $table->decimal('guest_egg_price', 8, 2)->default(50);
            $table->decimal('guest_veg_price', 8, 2)->default(45);
            $table->decimal('monthly_due_amount', 10, 2)->default(0);
            $table->unsignedTinyInteger('monthly_due_day')->default(5);
            $table->decimal('late_fee_amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messes');
    }
};
