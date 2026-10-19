<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FineModel extends Model
{
    protected $table = 'fines';

    protected $fillable = [
        'mess_id',
        'user_id',
        'mess_rule_id',
        'reason',
        'amount',
        'status',
        'fined_on',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'fined_on' => 'date',
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

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MessRuleModel::class, 'mess_rule_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'issued_by');
    }
}
