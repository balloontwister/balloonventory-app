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
}
