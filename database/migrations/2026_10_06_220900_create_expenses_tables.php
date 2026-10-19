<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constant_expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->enum('fund_type', ['mess_fund', 'manager_fund'])->default('manager_fund');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->enum('fund_type', ['mess_fund', 'manager_fund'])->default('manager_fund');
            $table->enum('category', ['market', 'constant', 'feast', 'tour', 'other'])->default('other');
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('spent_on');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('constant_expense_id')->nullable()->index();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();

            $table->index(['mess_id', 'spent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_entries');
        Schema::dropIfExists('constant_expenses');
    }
};
