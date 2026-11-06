<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLogModel extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'mess_id',
        'message',
        'meta',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'subject_id' => 'integer',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'actor_id');
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    /**
     * Record an admin-visible write for the Logs screen and Overview.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function record(
        string $action,
        string $message,
        ?int $actorId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $messId = null,
        array $meta = [],
        ?string $ip = null,
    ): self {
        return self::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'mess_id' => $messId,
            'message' => $message,
            'meta' => $meta === [] ? null : $meta,
            'ip' => $ip,
        ]);
    }
}
