<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessInvitationModel extends Model
{
    protected $table = 'mess_invitations';

    protected $fillable = [
        'invited_by',
        'plan_id',
        'mess_name',
        'email',
        'mobile',
        'token',
        'status',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'invited_by');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanModel::class, 'plan_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(MessSubscriptionModel::class, 'invitation_id');
    }
}
