<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessMemberModel extends Model
{
    protected $table = 'mess_members';

    protected $fillable = [
        'mess_id',
        'user_id',
        'president',
        'manager',
        'member',
    ];

    protected function casts(): array
    {
        return [
            'president' => 'boolean',
            'manager' => 'boolean',
            'member' => 'boolean',
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

    /**
     * @return array{president: bool, manager: bool, member: bool}
     */
    public function flags(): array
    {
        return [
            'president' => (bool) $this->president,
            'manager' => (bool) $this->manager,
            'member' => (bool) $this->member,
        ];
    }
}
