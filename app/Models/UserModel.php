<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Database\Factories\UserFactory;

class UserModel extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mess_id',
        'name',
        'email',
        'google_id',
        'mobile',
        'room_no',
        'member_code',
        'password',
        'role',
        'otp',
        'otp_expire_at',
        'is_loggedin',
        'status',
        'joining_date',
        'avatar',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'otp',
        'avatar',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'otp_expire_at' => 'datetime',
            'is_loggedin' => 'boolean',
            'joining_date' => 'datetime',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPresident(): bool
    {
        return $this->role === 'president';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isMember(): bool
    {
        return $this->role === 'member';
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(DepositModel::class, 'user_id');
    }

    public function dailyMeals(): HasMany
    {
        return $this->hasMany(DailyMealModel::class, 'user_id');
    }

    public function messesAsPresident(): HasMany
    {
        return $this->hasMany(MessModel::class, 'president_user_id');
    }

    public function messesAsManager(): HasMany
    {
        return $this->hasMany(MessModel::class, 'manager_user_id');
    }

    public function depositsAdded(): HasMany
    {
        return $this->hasMany(DepositModel::class, 'added_by');
    }

    public function purchasedSubscriptions(): HasMany
    {
        return $this->hasMany(MessSubscriptionModel::class, 'purchased_by');
    }
}
