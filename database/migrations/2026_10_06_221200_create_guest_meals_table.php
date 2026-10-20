<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // guest_meals was removed. Meal prices live in guest_meal_setup.
    }

    public function down(): void
    {
    }
};
