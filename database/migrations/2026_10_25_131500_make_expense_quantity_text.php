<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('expense_entries', 'quantity')) {
            return;
        }

        Schema::table('expense_entries', function (Blueprint $table) {
            $table->string('quantity', 100)->default('1')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('expense_entries', 'quantity')) {
            return;
        }

        Schema::table('expense_entries', function (Blueprint $table) {
            $table->decimal('quantity', 10, 2)->default(1)->change();
        });
    }
};
