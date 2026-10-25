<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseEntryModel extends Model
{
    protected $table = 'expense_entries';

    protected $fillable = [
        'mess_id',
        'fund_type',
        'category',
        'title',
        'quantity',
        'amount',
        'spent_on',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_on' => 'date',
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
