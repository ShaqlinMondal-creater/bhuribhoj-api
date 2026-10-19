<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('email_registrations');
        DB::statement('ALTER TABLE users MODIFY otp VARCHAR(64) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY otp VARCHAR(10) NULL');
    }
};
