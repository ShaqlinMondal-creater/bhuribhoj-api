<?php

namespace App\Models;

use App\Casts\KolkataDate;
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
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deposited_on' => KolkataDate::class,
            'for_month' => KolkataDate::class,
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

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'added_by');
    }
}
