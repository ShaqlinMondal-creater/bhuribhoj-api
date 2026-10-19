<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConstantExpenseModel extends Model
{
    protected $table = 'constant_expenses';

    protected $fillable = [
        'mess_id',
        'name',
        'amount',
        'fund_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(ExpenseEntryModel::class, 'constant_expense_id');
    }
}
