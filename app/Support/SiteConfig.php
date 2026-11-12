<?php

namespace App\Support;

use App\Models\PlatformConfigModel;

/**
 * Public site identity values stored in platform_configs (Admin → Configs).
 * Keys are lowercase because configsSave stores config_key in lowercase.
 */
final class SiteConfig
{
    public const CONTACT_EMAIL = 'public_contact_email';

    public const SITE_NAME = 'public_site_name';

    public const SITE_NAME_BN = 'public_site_bengali';

    public const SITE_BLURB = 'public_footer_text';

    public const SITE_COPYRIGHT = 'copyright_text';

    public const LEGAL_PRIVACY = 'legal_privacy';

    public const LEGAL_TERMS = 'legal_terms';

    public const CREDIT_NAME = 'credit_site_name';

    public const CREDIT_URL = 'credit_site_url';

    /** Older dotted keys — still read so existing rows keep working. */
    private const LEGACY = [
        self::CONTACT_EMAIL => ['contact.email'],
        self::SITE_NAME => ['site.name'],
        self::SITE_NAME_BN => ['site.name_bn'],
        self::SITE_BLURB => ['site.blurb'],
        self::SITE_COPYRIGHT => ['site.copyright'],
        self::LEGAL_PRIVACY => ['legal.privacy'],
        self::LEGAL_TERMS => ['legal.terms'],
        self::CREDIT_NAME => ['credit.name'],
        self::CREDIT_URL => ['credit.url'],
    ];

    /** @return array<string, string> */
    public static function defaults(): array
    {
        return [
            self::CONTACT_EMAIL => '',
            self::SITE_NAME => 'BhuriBhoj',
            self::SITE_NAME_BN => '',
            self::SITE_BLURB => 'A private ledger and kitchen book for the mess. Meals, money, and members, held in one house.',
            self::SITE_COPYRIGHT => '© 2026 BhuriBhoj',
            self::LEGAL_PRIVACY => "# Who can see a mess\nA mess ledger is visible to the people of that house, and to the platform admin who keeps the subscriptions.\n\n# Platform access\nThe platform admin can see subscription and house records needed to keep the service running.",
            self::LEGAL_TERMS => "# One subscription\nOne subscription covers one mess, for a month, a semester, or a year.\n\n# Who opens the house\nThe president opens it and manages the seat for that mess.",
            self::CREDIT_NAME => 'liwaas.com',
            self::CREDIT_URL => 'https://liwaas.com',
        ];
    }

    /**
     * @return array{
     *   contact_email: string|null,
     *   site_name: string,
     *   site_name_bn: string|null,
     *   site_blurb: string,
     *   site_copyright: string,
     *   legal_privacy: string,
     *   legal_terms: string,
     *   credit_name: string,
     *   credit_url: string
     * }
     */
    public static function public(): array
    {
        $defaults = self::defaults();
        $wanted = [];
        foreach (array_keys($defaults) as $key) {
            $wanted[] = $key;
            foreach (self::LEGACY[$key] ?? [] as $legacy) {
                $wanted[] = $legacy;
            }
        }

        $rows = PlatformConfigModel::query()
            ->whereIn('config_key', array_values(array_unique($wanted)))
            ->pluck('config_value', 'config_key');

        $read = static function (string $key) use ($rows, $defaults): string {
            $candidates = array_merge([$key], self::LEGACY[$key] ?? []);
            foreach ($candidates as $candidate) {
                $raw = $rows->get($candidate);
                if (! is_string($raw)) {
                    continue;
                }
                $value = trim($raw);
                if ($value !== '') {
                    return $value;
                }
            }

            return $defaults[$key];
        };

        /**
         * Optional fields: blank or missing = hidden (null).
         * If the primary key row exists (even empty), do not fall back to legacy/defaults.
         */
        $readOptional = static function (string $key) use ($rows): ?string {
            if ($rows->has($key)) {
                $raw = $rows->get($key);
                if (! is_string($raw)) {
                    return null;
                }
                $value = trim($raw);

                return $value !== '' ? $value : null;
            }

            foreach (self::LEGACY[$key] ?? [] as $candidate) {
                if (! $rows->has($candidate)) {
                    continue;
                }
                $raw = $rows->get($candidate);
                if (! is_string($raw)) {
                    return null;
                }
                $value = trim($raw);

                return $value !== '' ? $value : null;
            }

            return null;
        };

        // Blank/missing public_contact_email stays hidden — no CONTACT_INBOX override.
        return [
            'contact_email' => $readOptional(self::CONTACT_EMAIL),
            'site_name' => $read(self::SITE_NAME),
            'site_name_bn' => $readOptional(self::SITE_NAME_BN),
            'site_blurb' => $read(self::SITE_BLURB),
            'site_copyright' => $read(self::SITE_COPYRIGHT),
            'legal_privacy' => $read(self::LEGAL_PRIVACY),
            'legal_terms' => $read(self::LEGAL_TERMS),
            'credit_name' => $read(self::CREDIT_NAME),
            'credit_url' => self::absoluteHttpUrl($read(self::CREDIT_URL), $defaults[self::CREDIT_URL]),
        ];
    }

    public static function contactInbox(): string
    {
        return (string) (self::public()['contact_email'] ?? '');
    }

    /**
     * Bare hosts like "liwaas.com" must become https://… or browsers treat them as relative paths.
     */
    private static function absoluteHttpUrl(string $raw, string $fallback): string
    {
        $value = trim($raw);
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('#^https?://#i', $value) === 1) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return 'https:'.$value;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $value) === 1) {
            return $fallback;
        }

        return 'https://'.ltrim($value, '/');
    }
}
