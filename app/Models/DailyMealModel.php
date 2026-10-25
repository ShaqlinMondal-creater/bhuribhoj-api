<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMealModel extends Model
{
    protected $table = 'daily_meals';

    protected $fillable = [
        'mess_id',
        'user_id',
        'meal_date',
        'breakfast_active',
        'breakfast_taken',
        'morning_snack_active',
        'morning_snack_taken',
        'lunch_active',
        'lunch_taken',
        'evening_snack_active',
        'evening_snack_taken',
        'dinner_active',
        'dinner_taken',
        'late_night_active',
        'late_night_taken',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'meal_date' => 'date',
            'breakfast_active' => 'boolean',
            'breakfast_taken' => 'boolean',
            'morning_snack_active' => 'boolean',
            'morning_snack_taken' => 'boolean',
            'lunch_active' => 'boolean',
            'lunch_taken' => 'boolean',
            'evening_snack_active' => 'boolean',
            'evening_snack_taken' => 'boolean',
            'dinner_active' => 'boolean',
            'dinner_taken' => 'boolean',
            'late_night_active' => 'boolean',
            'late_night_taken' => 'boolean',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'marked_by');
    }
}
