<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // mess_rules and fines were removed. No screen uses them.
    }

    public function down(): void
    {
    }
};
