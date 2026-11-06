<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mess_subscriptions')) {
            return;
        }

        Schema::table('mess_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('mess_subscriptions', 'coupon_id')) {
                $table->foreignId('coupon_id')->nullable()->after('plan_id')->constrained('coupons')->nullOnDelete();
            }
            if (! Schema::hasColumn('mess_subscriptions', 'discount_inr')) {
                $table->unsignedInteger('discount_inr')->default(0)->after('amount_paid_inr');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mess_subscriptions')) {
            return;
        }

        Schema::table('mess_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('mess_subscriptions', 'coupon_id')) {
                $table->dropConstrainedForeignId('coupon_id');
            }
            if (Schema::hasColumn('mess_subscriptions', 'discount_inr')) {
                $table->dropColumn('discount_inr');
            }
        });
    }
};
