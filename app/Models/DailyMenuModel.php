<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyMenuModel extends Model
{
    protected $table = 'daily_menus';

    protected $fillable = [
        'mess_id',
        'menu_date',
        'meal_type',
        'dishes',
        'decided_by',
    ];

    protected function casts(): array
    {
        return [
            'menu_date' => 'date',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'decided_by');
    }
}
