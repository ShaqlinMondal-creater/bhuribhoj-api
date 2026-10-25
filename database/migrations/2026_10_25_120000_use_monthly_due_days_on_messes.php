<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('messes', 'monthly_due_date') && ! Schema::hasColumn('messes', 'monthly_due_days')) {
            Schema::table('messes', function (Blueprint $table) {
                $table->unsignedTinyInteger('monthly_due_days')->nullable()->after('monthly_due_amount');
            });

            foreach (DB::table('messes')->select('id', 'monthly_due_date')->get() as $mess) {
                $day = $this->dayFrom($mess->monthly_due_date);

                if ($day !== null) {
                    DB::table('messes')->where('id', $mess->id)->update(['monthly_due_days' => $day]);
                }
            }

            Schema::table('messes', function (Blueprint $table) {
                $table->dropColumn('monthly_due_date');
            });
        }

        Schema::table('messes', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('messes', 'monthly_due_days') && ! Schema::hasColumn('messes', 'monthly_due_date')) {
            Schema::table('messes', function (Blueprint $table) {
                $table->string('monthly_due_date', 10)->nullable()->after('monthly_due_amount');
            });

            foreach (DB::table('messes')->select('id', 'monthly_due_days')->get() as $mess) {
                $day = (int) $mess->monthly_due_days;

                if ($day >= 1 && $day <= 31) {
                    DB::table('messes')->where('id', $mess->id)->update([
                        'monthly_due_date' => sprintf('%02d-01-2000', $day),
                    ]);
                }
            }

            Schema::table('messes', function (Blueprint $table) {
                $table->dropColumn('monthly_due_days');
            });
        }
    }

    private function dayFrom(mixed $value): ?int
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})-\d{1,2}-\d{4}$/', $text, $match) || preg_match('/^\d{4}-\d{2}-(\d{2})/', $text, $match)) {
            $day = (int) $match[1];

            return $day >= 1 && $day <= 31 ? $day : null;
        }

        if (ctype_digit($text)) {
            $day = (int) $text;

            return $day >= 1 && $day <= 31 ? $day : null;
        }

        return null;
    }
};
