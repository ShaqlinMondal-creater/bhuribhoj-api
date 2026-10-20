<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // monthly_dues was removed. No screen uses it.
    }

    public function down(): void
    {
    }
};
