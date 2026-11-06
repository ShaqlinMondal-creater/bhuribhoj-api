<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformConfigModel extends Model
{
    protected $table = 'platform_configs';

    protected $fillable = [
        'config_key',
        'config_value',
        'note',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'updated_by');
    }
}
