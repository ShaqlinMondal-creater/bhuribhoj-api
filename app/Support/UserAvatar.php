<?php

namespace App\Support;

use App\Models\UserModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UserAvatar
{
    private const MAX_BYTES = 512_000;

    public static function publicUrl(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (str_starts_with($stored, 'data:image/')) {
            return $stored;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        return rtrim((string) config('app.url'), '/').'/storage/'.ltrim($stored, '/');
    }

    /**
     * Return a public URL. Legacy base64 rows are moved to disk once.
     */
    public static function hydrate(UserModel $user): ?string
    {
        $raw = $user->getAttributes()['avatar'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }

        if (str_starts_with($raw, 'data:image/')) {
            try {
                $path = self::storeDataUrl($user, $raw, deleteExisting: false);
                $user->forceFill(['avatar' => $path])->save();

                return self::publicUrl($path);
            } catch (\Throwable) {
                return $raw;
            }
        }

        return self::publicUrl($raw);
    }

    public static function storeDataUrl(UserModel $user, string $dataUrl, bool $deleteExisting = true): string
    {
        if (! preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'avatar' => ['Avatar must be a JPEG, PNG, or WebP image.'],
            ]);
        }

        $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);

        if ($binary === false || $binary === '') {
            throw ValidationException::withMessages([
                'avatar' => ['Avatar image data is invalid.'],
            ]);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'avatar' => ['Avatar is too large. Use a smaller photo.'],
            ]);
        }

        if ($deleteExisting) {
            self::deleteStored($user->getAttributes()['avatar'] ?? null);
        }

        $path = 'avatars/'.$user->id.'_'.Str::lower(Str::random(8)).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    public static function clear(UserModel $user): void
    {
        self::deleteStored($user->getAttributes()['avatar'] ?? null);
        $user->avatar = null;
    }

    public static function deleteStored(?string $stored): void
    {
        if ($stored === null || $stored === '') {
            return;
        }

        if (str_starts_with($stored, 'data:') || str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return;
        }

        $path = ltrim($stored, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (! str_starts_with($path, 'avatars/')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
