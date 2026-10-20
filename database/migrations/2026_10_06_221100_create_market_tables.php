<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // market_purchases and market_duties were removed. No screen uses them.
    }

    public function down(): void
    {
    }
};
