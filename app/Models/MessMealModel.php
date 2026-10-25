<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessMealModel extends Model
{
    public const TYPES = ['breakfast', 'lunch', 'dinner'];

    protected $table = 'mess_meals';

    protected $fillable = [
        'mess_id',
        'meals_type',
        'added_by',
        'status',
    ];

    public function mess(): BelongsTo
    {
        return $this->belongsTo(MessModel::class, 'mess_id');
    }

    public function adder(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'added_by');
    }

    public static function ensure(int $messId): void
    {
        foreach (self::TYPES as $type) {
            self::query()->firstOrCreate(
                ['mess_id' => $messId, 'meals_type' => $type],
                [
                    'status' => $type === 'breakfast' ? 'inactive' : 'active',
                    'added_by' => null,
                ]
            );
        }
    }

    public static function activeTypes(int $messId): array
    {
        $rows = self::query()->where('mess_id', $messId)->get(['meals_type', 'status']);

        if ($rows->isEmpty()) {
            self::ensure($messId);

            return ['lunch', 'dinner'];
        }

        $active = $rows->where('status', 'active')->pluck('meals_type')->all();

        return array_values(array_filter(self::TYPES, fn (string $type) => in_array($type, $active, true)));
    }
}
