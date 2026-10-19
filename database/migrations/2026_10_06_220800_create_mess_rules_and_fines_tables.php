<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('fine_amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('mess_rule_id')->nullable()->index();
            $table->string('reason');
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'paid', 'waived'])->default('pending');
            $table->date('fined_on');
            $table->unsignedBigInteger('issued_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fines');
        Schema::dropIfExists('mess_rules');
    }
};
