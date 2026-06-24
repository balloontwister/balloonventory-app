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
     * Canonicalise the many ways a balloon size is written so they compare
     * equal: "11 inch", "11-inch", "11inch", "11 inches", and 11" / 11” / 11″
     * all become "11in". Applied to both the product text and the catalog size
     * name before matching, so a distributor's "11 inch" lines up with our
     * "11-inch" reference name. Non-size text is left untouched.
     */
    public static function normalizeSizeTokens(string $text): string
    {
        $text = preg_replace('/(\d+)\s*-?\s*inch(?:es)?\b/i', '${1}in', $text);
        $text = preg_replace('/(\d+)\s*["”″]/u', '${1}in', $text);
        $text = preg_replace('/(\d+)-in\b/i', '${1}in', $text);

        return $text;
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
