<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessMealModel extends Model
{
    public const TYPES = ['breakfast', 'morning_snack', 'lunch', 'evening_snack', 'dinner', 'late_night'];

    public const LABELS = [
        'breakfast' => 'Breakfast',
        'morning_snack' => 'Morning Snack',
        'lunch' => 'Lunch',
        'evening_snack' => 'Evening Snack',
        'dinner' => 'Dinner',
        'late_night' => 'Late-Night Snack',
    ];

    public const RANKS = [
        'breakfast' => '0',
        'morning_snack' => '1',
        'lunch' => '2',
        'evening_snack' => '3',
        'dinner' => '4',
        'late_night' => '5',
    ];

    public const LOCKS = [
        'breakfast' => '06:00',
        'morning_snack' => '10:00',
        'lunch' => '12:30',
        'evening_snack' => '17:30',
        'dinner' => '21:00',
    ];

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
                    'status' => in_array($type, ['lunch', 'dinner'], true) ? 'active' : 'inactive',
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
