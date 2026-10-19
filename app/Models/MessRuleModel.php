<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessRuleModel extends Model
{
    protected $table = 'mess_rules';

    protected $fillable = [
        'mess_id',
        'title',
        'description',
        'fine_amount',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fine_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function fines(): HasMany
    {
        return $this->hasMany(FineModel::class, 'mess_rule_id');
    }
}
