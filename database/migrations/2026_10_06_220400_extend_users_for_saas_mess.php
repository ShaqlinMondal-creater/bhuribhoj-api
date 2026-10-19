<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('mess_id')->nullable()->after('id')->index();
            $table->string('mobile', 20)->nullable()->after('email');
            $table->string('room_no', 32)->nullable()->after('mobile');
            $table->string('member_code', 32)->nullable()->after('room_no');
            $table->string('otp', 10)->nullable()->after('password');
            $table->timestamp('otp_expire_at')->nullable()->after('otp');
            $table->boolean('is_loggedin')->default(false)->after('otp_expire_at');
            $table->enum('status', ['active', 'inactive'])->default('active')->after('is_loggedin');
            $table->timestamp('joining_date')->nullable()->after('status');
        });

        if (Schema::hasColumn('users', 'role')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','president','manager','member') NOT NULL DEFAULT 'member'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'president', 'manager', 'member'])
                    ->default('member')
                    ->after('password');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['mess_id', 'member_code']);
            $table->index(['mess_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mess_id', 'member_code']);
            $table->dropIndex(['mess_id', 'role']);
            $table->dropColumn([
                'mess_id',
                'mobile',
                'room_no',
                'member_code',
                'otp',
                'otp_expire_at',
                'is_loggedin',
                'status',
                'joining_date',
                'role',
            ]);
        });
    }
};
