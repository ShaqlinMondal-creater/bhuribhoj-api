<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPurchaseModel extends Model
{
    protected $table = 'market_purchases';

    protected $fillable = [
        'mess_id',
        'purchase_date',
        'item_name',
        'quantity',
        'unit',
        'amount',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'quantity' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'recorded_by');
    }
}
