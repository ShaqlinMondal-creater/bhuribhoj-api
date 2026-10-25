<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessModel extends Model
{
    use HasFactory;

    protected $table = 'messes';

    protected $fillable = [
        'name',
        'code',
        'address',
        'district',
        'pincode',
        'contact_no',
        'email',
        'fund_type',
        'president_user_id',
        'manager_user_id',
        'guest_meal_setup_id',
        'monthly_due_amount',
        'monthly_due_days',
        's_deposite_amount',
        'late_fee_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_due_amount' => 'decimal:2',
            'monthly_due_days' => 'integer',
            's_deposite_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hasMessFund(): bool
    {
        return $this->fund_type === 'mess_and_manager';
    }

    public function users(): HasMany
    {
        return $this->hasMany(UserModel::class, 'mess_id');
    }

    public function president(): HasOne
    {
        return $this->hasOne(UserModel::class, 'mess_id')->where('role', 'president');
    }

    public function presidentUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'president_user_id');
    }

    public function managerUser(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'manager_user_id');
    }

    public function guestMealSetup(): BelongsTo
    {
        return $this->belongsTo(GuestMealSetupModel::class, 'guest_meal_setup_id');
    }

    public function guestMealSetups(): HasMany
    {
        return $this->hasMany(GuestMealSetupModel::class, 'mess_id');
    }

    public function pmDetails(): HasMany
    {
        return $this->hasMany(MessPmDetailModel::class, 'mess_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MessSubscriptionModel::class, 'mess_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(MessSubscriptionModel::class, 'mess_id')
            ->ofMany(['id' => 'max'], function ($query) {
                $query->where('status', 'active')
                    ->whereDate('ends_on', '>=', now()->toDateString());
            });
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(DepositModel::class, 'mess_id');
    }

    public function expenseEntries(): HasMany
    {
        return $this->hasMany(ExpenseEntryModel::class, 'mess_id');
    }

    public function dailyMeals(): HasMany
    {
        return $this->hasMany(DailyMealModel::class, 'mess_id');
    }

    public function dailyMenus(): HasMany
    {
        return $this->hasMany(DailyMenuModel::class, 'mess_id');
    }
}
