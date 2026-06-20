<?php

namespace App\Support;

use Locale;

/**
 * Resolves ISO 3166-1 alpha-2 country codes to localized display names via the
 * intl extension. The canonical code list lives in config/countries.php; names
 * are derived at runtime so they're localized for free (en + es) and we don't
 * maintain a name table.
 */
class Countries
{
    /**
     * Code => localized display name, sorted by name.
     *
     * @return array<string, string>
     */
    public static function all(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        $map = [];
        foreach (config('countries', []) as $code) {
            $map[$code] = Locale::getDisplayRegion('-'.$code, $locale);
        }

        asort($map);

        return $map;
    }

    /**
     * The localized display name for a single code, or null when not set.
     */
    public static function name(?string $code, ?string $locale = null): ?string
    {
        if (! $code) {
            return null;
        }

        $locale ??= app()->getLocale();

        return Locale::getDisplayRegion('-'.$code, $locale);
    }

    /**
     * Whether a code is a recognized ISO 3166-1 alpha-2 country code.
     */
    public static function isValid(string $code): bool
    {
        return in_array($code, config('countries', []), true);
    }
}
