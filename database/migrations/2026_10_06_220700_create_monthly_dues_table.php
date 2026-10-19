<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_dues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->char('month', 7);
            $table->decimal('amount', 12, 2);
            $table->date('due_on');
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->enum('status', ['pending', 'paid', 'overdue', 'waived'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['mess_id', 'user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_dues');
    }
};
