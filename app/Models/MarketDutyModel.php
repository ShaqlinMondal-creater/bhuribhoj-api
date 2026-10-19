<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketDutyModel extends Model
{
    protected $table = 'market_duties';

    protected $fillable = [
        'mess_id',
        'user_id',
        'duty_date',
        'status',
        'assigned_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'duty_date' => 'date',
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

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'assigned_by');
    }
}
