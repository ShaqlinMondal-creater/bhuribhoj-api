<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestMealModel extends Model
{
    protected $table = 'guest_meals';

    protected $fillable = [
        'mess_id',
        'member_id',
        'guest_type',
        'dish_type',
        'quantity',
        'unit_price',
        'total_amount',
        'meal_date',
        'meal_type',
        'guest_name',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'meal_date' => 'date',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'member_id');
    }
}
