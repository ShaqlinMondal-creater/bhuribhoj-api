<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositModel extends Model
{
    protected $table = 'deposits';

    protected $fillable = [
        'mess_id',
        'user_id',
        'fund_type',
        'deposit_type',
        'amount',
        'deposited_on',
        'for_month',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deposited_on' => 'date',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'recorded_by');
    }
}
