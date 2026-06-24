<?php

namespace App\Support;

/**
 * Helpers for pulling structured facts out of free-text product titles/slugs.
 */
class ProductText
{
    /**
     * Parse a pack/bag count from a product name — "50ct", "50 per bag",
     * "bag of 100", "(100 count)", etc. Returns null when none is present.
     */
    public static function packCount(string $name): ?int
    {
        if (preg_match('/(\d{1,4})\s*(?:ct|count|pcs|pc|pk)\b/i', $name, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d{1,4})[\s-]*per[\s-]*(?:bag|pack)/i', $name, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(?:bag|pack)\s*of\s*(\d{1,4})/i', $name, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Case-insensitive "name appears as a distinct token" test. Requires a
     * non-alphanumeric boundary (or string start) immediately before the needle
     * so "5-inch" doesn't match inside "15-inch" and "blue" doesn't match inside
     * a longer word. A trailing boundary is intentionally NOT required so
     * "5-inch" still matches "5-inches".
     */
    public static function mentions(string $haystack, string $needle): bool
    {
        $needle = trim($needle);

        if ($needle === '') {
            return false;
        }

        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'/i', $haystack);
    }
}
