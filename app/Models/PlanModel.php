<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanModel extends Model
{
    use HasFactory;

    protected $table = 'plans';

    protected $fillable = [
        'name',
        'slug',
        'duration_months',
        'price_inr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'duration_months' => 'integer',
            'price_inr' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(MessSubscriptionModel::class, 'plan_id');
    }
}
