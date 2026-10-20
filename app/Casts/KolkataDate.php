<?php

namespace App\Casts;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a calendar date as dd-mm-yyyy. Reads and writes stay in Asia/Kolkata.
 */
class KolkataDate implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalize((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->normalize($value);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->copy()->timezone('Asia/Kolkata')->format('d-m-Y');
        }

        $text = trim((string) $value);

        if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $text) === 1) {
            return $text;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $text) === 1) {
            return \Carbon\Carbon::parse($text, 'Asia/Kolkata')->timezone('Asia/Kolkata')->format('d-m-Y');
        }

        if (preg_match('/^\d{4}-\d{2}$/', $text) === 1) {
            return \Carbon\Carbon::parse($text.'-01', 'Asia/Kolkata')->timezone('Asia/Kolkata')->format('d-m-Y');
        }

        return \Carbon\Carbon::parse($text, 'Asia/Kolkata')->timezone('Asia/Kolkata')->format('d-m-Y');
    }
}
