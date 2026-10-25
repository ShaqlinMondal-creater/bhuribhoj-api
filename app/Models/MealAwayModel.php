<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealAwayModel extends Model
{
    protected $table = 'meal_aways';

    protected $fillable = [
        'mess_id',
        'user_id',
        'from_date',
        'from_meal',
        'to_date',
        'to_meal',
        'note',
        'set_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'set_by');
    }
}
