<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_inquiries')) {
            Schema::create('contact_inquiries', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('mess');
                $table->unsignedInteger('members');
                $table->string('phone', 40)->nullable();
                $table->string('email')->nullable();
                $table->text('message');
                $table->enum('status', ['open', 'replied', 'closed'])->default('open');
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contact_inquiry_replies')) {
            Schema::create('contact_inquiry_replies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contact_inquiry_id')->constrained('contact_inquiries')->cascadeOnDelete();
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->string('emailed_to')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiry_replies');
        Schema::dropIfExists('contact_inquiries');
    }
};
