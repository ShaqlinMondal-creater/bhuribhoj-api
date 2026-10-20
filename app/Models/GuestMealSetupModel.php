<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestMealSetupModel extends Model
{
    protected $table = 'guest_meal_setup';

    protected $fillable = [
        'meal_name',
        'price',
        'mess_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }
}
