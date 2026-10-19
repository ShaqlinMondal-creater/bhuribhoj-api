<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NULL');
        DB::statement('ALTER TABLE users MODIFY otp VARCHAR(64) NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });

        DB::statement('ALTER TABLE mess_subscriptions MODIFY mess_id BIGINT UNSIGNED NULL');
        DB::statement("ALTER TABLE mess_subscriptions MODIFY status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending'");

        Schema::table('mess_subscriptions', function (Blueprint $table) {
            $table->string('razorpay_order_id')->nullable()->unique()->after('amount_paid_inr');
            $table->string('razorpay_payment_id')->nullable()->unique()->after('razorpay_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('mess_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['razorpay_order_id', 'razorpay_payment_id']);
        });

        DB::statement("ALTER TABLE mess_subscriptions MODIFY status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active'");
        DB::statement('ALTER TABLE mess_subscriptions MODIFY mess_id BIGINT UNSIGNED NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });

        DB::statement('ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE users MODIFY otp VARCHAR(10) NULL');
    }
};
