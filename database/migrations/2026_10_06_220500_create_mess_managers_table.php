<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mess_managers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mess_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->char('month', 7);
            $table->timestamps();

            $table->unique(['mess_id', 'user_id', 'month']);
            $table->index(['mess_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mess_managers');
    }
};
