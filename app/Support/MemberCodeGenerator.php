<?php

namespace App\Support;

use App\Models\MessModel;
use App\Models\UserModel;
use Illuminate\Support\Str;

class MemberCodeGenerator
{
    /**
     * Paul Mess -> PM00001, North Line Boys -> NLB00001
     */
    public static function initialsFromMessName(string $name): string
    {
        $words = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $word) ?? '';
            if ($clean !== '') {
                $letters .= Str::upper(Str::substr($clean, 0, 1));
            }
        }

        return $letters !== '' ? $letters : 'M';
    }

    public static function next(MessModel $mess): string
    {
        $prefix = self::initialsFromMessName($mess->name);

        $latest = UserModel::query()
            ->where('mess_id', $mess->id)
            ->where('member_code', 'like', $prefix.'%')
            ->orderByDesc('member_code')
            ->value('member_code');

        $nextNumber = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
