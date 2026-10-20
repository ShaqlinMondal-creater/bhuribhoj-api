<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fines');
        Schema::dropIfExists('mess_rules');
        Schema::dropIfExists('guest_meals');
        Schema::dropIfExists('monthly_dues');
        Schema::dropIfExists('market_duties');
        Schema::dropIfExists('market_purchases');
        Schema::dropIfExists('constant_expenses');
        Schema::dropIfExists('mess_managers');
        Schema::dropIfExists('mess_invitations');

        Schema::table('expense_entries', function (Blueprint $table) {
            if (Schema::hasColumn('expense_entries', 'constant_expense_id')) {
                $table->dropColumn('constant_expense_id');
            }
        });

        Schema::table('mess_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('mess_subscriptions', 'invitation_id')) {
                $table->dropColumn('invitation_id');
            }
        });
    }

    public function down(): void
    {
        // These tables were removed because no screen uses them.
    }
};
