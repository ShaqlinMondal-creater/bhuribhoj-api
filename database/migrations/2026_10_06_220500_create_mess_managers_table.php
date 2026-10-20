<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // mess_managers was removed. President and manager history lives in mess_p_m_details.
    }

    public function down(): void
    {
    }
};
