<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->enum('purchase_source', ['admin_invite', 'self_purchase'])->default('self_purchase');
            $table->unsignedBigInteger('purchased_by')->nullable()->index();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->unsignedInteger('amount_paid_inr')->nullable();
            $table->timestamps();

            $table->index(['mess_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_subscriptions');
    }
};
