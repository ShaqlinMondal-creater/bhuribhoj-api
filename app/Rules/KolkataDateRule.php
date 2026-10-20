<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class KolkataDateRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^\d{2}-\d{2}-\d{4}$/', $value) !== 1) {
            $fail('The '.$attribute.' must use dd-mm-yyyy.');

            return;
        }

        $date = Carbon::createFromFormat('d-m-Y', $value, 'Asia/Kolkata');

        if (! $date || $date->format('d-m-Y') !== $value) {
            $fail('The '.$attribute.' must be a real date in dd-mm-yyyy.');
        }
    }
}
