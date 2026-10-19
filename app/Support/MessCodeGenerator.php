<?php

namespace App\Support;

use App\Models\MessModel;
use Illuminate\Support\Str;

class MessCodeGenerator
{
    public static function fromName(string $name): string
    {
        $base = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '');
        $base = $base !== '' ? Str::substr($base, 0, 16) : 'MESS';

        $code = $base;
        $suffix = 1;

        while (MessModel::query()->where('code', $code)->exists()) {
            $code = Str::substr($base, 0, 12).$suffix;
            $suffix++;
        }

        return $code;
    }
}
