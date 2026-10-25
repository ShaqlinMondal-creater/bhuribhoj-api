<?php

namespace Database\Seeders;

use App\Models\MessMealModel;
use App\Models\MessModel;
use App\Models\MessSubscriptionModel;
use App\Models\PlanModel;
use App\Models\UserModel;
use App\Support\MemberCodeGenerator;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => '1 Month', 'slug' => '1-month', 'duration_months' => 1, 'price_inr' => 99],
            ['name' => '3 Months', 'slug' => '3-months', 'duration_months' => 3, 'price_inr' => 279],
            ['name' => '1 Year', 'slug' => '1-year', 'duration_months' => 12, 'price_inr' => 999],
        ] as $plan) {
            PlanModel::query()->updateOrCreate(['slug' => $plan['slug']], $plan);
        }

        UserModel::query()->updateOrCreate(
            ['email' => 'admin@bhuribhoj.local'],
            [
                'mess_id' => null,
                'name' => 'Platform Admin',
                'password' => 'Admin@123',
                'role' => 'admin',
                'status' => 'active',
                'is_loggedin' => false,
                'member_code' => null,
            ],
        );

        $yearPlan = PlanModel::query()->where('slug', '1-year')->firstOrFail();

        $mess = MessModel::query()->updateOrCreate(
            ['code' => 'PAULMESS'],
            [
                'name' => 'Paul Mess',
                'address' => '12 Hostel Lane',
                'district' => 'Kolkata',
                'pincode' => '700001',
                'contact_no' => '9876543210',
                'email' => 'paulmess@example.com',
                'fund_type' => 'mess_and_manager',
                'monthly_due_amount' => 1500,
                'monthly_due_days' => 5,
                'late_fee_amount' => 50,
                'is_active' => true,
            ],
        );

        MessSubscriptionModel::query()->updateOrCreate(
            [
                'mess_id' => $mess->id,
                'plan_id' => $yearPlan->id,
                'status' => 'active',
            ],
            [
                'purchase_source' => 'admin_invite',
                'purchased_by' => null,
                'starts_on' => now()->toDateString(),
                'ends_on' => now()->addYear()->toDateString(),
                'amount_paid_inr' => $yearPlan->price_inr,
            ],
        );

        $president = UserModel::query()->firstOrNew(['email' => 'president@paulmess.local']);
        $president->fill([
            'mess_id' => $mess->id,
            'name' => 'Paul President',
            'mobile' => '9000000001',
            'room_no' => 'P1',
            'password' => 'President@123',
            'role' => 'president',
            'status' => 'active',
            'joining_date' => now(),
            'is_loggedin' => false,
        ]);

        if (! $president->member_code) {
            $president->member_code = MemberCodeGenerator::next($mess);
        }

        $president->save();

        $manager = UserModel::query()->firstOrNew(['email' => 'manager@paulmess.local']);
        $manager->fill([
            'mess_id' => $mess->id,
            'name' => 'Paul Manager',
            'mobile' => '9000000002',
            'room_no' => 'M1',
            'password' => 'Manager@123',
            'role' => 'manager',
            'status' => 'active',
            'joining_date' => now(),
            'is_loggedin' => false,
        ]);

        if (! $manager->member_code) {
            $manager->member_code = MemberCodeGenerator::next($mess);
        }

        $manager->save();

        $member = UserModel::query()->firstOrNew(['email' => 'member@paulmess.local']);
        $member->fill([
            'mess_id' => $mess->id,
            'name' => 'Paul Member',
            'mobile' => '9000000003',
            'room_no' => '12',
            'password' => 'Member@123',
            'role' => 'member',
            'status' => 'active',
            'joining_date' => now(),
            'is_loggedin' => false,
        ]);

        if (! $member->member_code) {
            $member->member_code = MemberCodeGenerator::next($mess);
        }

        $member->save();

        MessMealModel::ensure($mess->id);
        MessMealModel::query()->where('mess_id', $mess->id)->whereNull('added_by')->update(['added_by' => $president->id]);
    }
}
