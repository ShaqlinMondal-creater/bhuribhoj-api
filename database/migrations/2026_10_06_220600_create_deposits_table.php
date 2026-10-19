<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('fund_type', ['mess_fund', 'manager_fund']);
            $table->enum('deposit_type', ['join', 'monthly', 'extra'])->default('extra');
            $table->decimal('amount', 12, 2);
            $table->date('deposited_on');
            $table->char('for_month', 7)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();

            $table->index(['mess_id', 'fund_type', 'deposited_on']);
            $table->index(['user_id', 'for_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
