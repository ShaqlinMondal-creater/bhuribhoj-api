<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_purchases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->date('purchase_date');
            $table->string('item_name');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('unit', 32)->nullable();
            $table->decimal('amount', 12, 2);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable()->index();
            $table->timestamps();

            $table->index(['mess_id', 'purchase_date']);
        });

        Schema::create('market_duties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('duty_date');
            $table->enum('status', ['assigned', 'done', 'missed', 'swapped'])->default('assigned');
            $table->unsignedBigInteger('assigned_by')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['mess_id', 'user_id', 'duty_date']);
            $table->index(['mess_id', 'duty_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_duties');
        Schema::dropIfExists('market_purchases');
    }
};
