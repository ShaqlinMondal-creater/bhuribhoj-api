<?php

namespace App\Models;

use App\Casts\KolkataDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessPmDetailModel extends Model
{
    protected $table = 'mess_p_m_details';

    protected $fillable = [
        'mess_id',
        'president_user_id',
        'manager_user_id',
        'to_date',
        'from_date',
    ];

    protected function casts(): array
    {
        return [
            'to_date' => KolkataDate::class,
            'from_date' => KolkataDate::class,
        ];
    }

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function president(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'president_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'manager_user_id');
    }
}
