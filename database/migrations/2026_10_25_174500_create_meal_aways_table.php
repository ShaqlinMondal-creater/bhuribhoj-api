<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_aways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mess_id')->constrained('messes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('from_date');
            $table->enum('from_meal', ['lunch', 'dinner']);
            $table->date('to_date')->nullable();
            $table->enum('to_meal', ['lunch', 'dinner'])->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mess_id', 'user_id', 'from_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_aways');
    }
};
