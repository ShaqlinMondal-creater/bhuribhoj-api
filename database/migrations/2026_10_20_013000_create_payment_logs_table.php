<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('mess_subscription_id')->nullable()->constrained('mess_subscriptions');
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('razorpay_order_id');
            $table->string('razorpay_payment_id')->nullable();
            $table->unsignedInteger('amount_inr');
            $table->enum('status', ['created', 'failed', 'paid']);
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['razorpay_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
