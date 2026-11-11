<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messes')) {
            return;
        }

        Schema::table('messes', function (Blueprint $table) {
            if (! Schema::hasColumn('messes', 'theme')) {
                $table->string('theme', 32)->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('messes') || ! Schema::hasColumn('messes', 'theme')) {
            return;
        }

        Schema::table('messes', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
