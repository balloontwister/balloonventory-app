<?php

namespace App\Services;

use App\Models\BarcodeLinkAudit;
use App\Models\Sku;
use App\Support\Gtin;
use Illuminate\Validation\ValidationException;

/**
 * Writes a manufacturer barcode onto a shared-catalog SKU, with the validation
 * the catalog depends on (one barcode → one product), and records an append-only
 * audit row. Shared by the scan page (a business user) and the distributor
 * proposal "map to existing" flow (an admin acting on the shared catalog, with a
 * null business). The `source` distinguishes the two in the audit log.
 */
class BarcodeLinker
{
    public const SOURCE_SCAN = 'scan';

    public const SOURCE_ADMIN = 'admin';

    /**
     * Link $barcode to $sku, returning the column it was written to (`upc`/`ean`).
     *
     * @throws ValidationException when the barcode is invalid, already on another
     *                             SKU, or would clobber a different code on this one.
     */
    public function link(Sku $sku, string $barcode, ?string $businessId, ?string $userId, string $source): string
    {
        $digits = Gtin::digitsOnly($barcode);

        // A US UPC-A scanned (or stored) as a 13-digit EAN-13 with a leading zero
        // (GS1 prefixes 0-09 are the UPC-A namespace) collapses back to its
        // 12-digit UPC-A form; the mod-10 check digit is unchanged.
        if (strlen($digits) === 13 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) < 8 || ! Gtin::isValidCheckDigit($digits)) {
            throw ValidationException::withMessages([
                'barcode' => __('scan.link.invalid_barcode'),
            ]);
        }

        $column = strlen($digits) === 12 ? 'upc' : 'ean';

        $clash = Sku::where('id', '!=', $sku->id)
            ->where(fn ($q) => $q->where('upc', $digits)->orWhere('ean', $digits))
            ->first();

        if ($clash !== null) {
            throw ValidationException::withMessages([
                'barcode' => __('scan.link.already_used', ['name' => $clash->name]),
            ]);
        }

        if ($sku->{$column} !== null && $sku->{$column} !== $digits) {
            throw ValidationException::withMessages([
                'barcode' => __('scan.link.has_other_code'),
            ]);
        }

        $sku->{$column} = $digits;
        $sku->save();

        BarcodeLinkAudit::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'sku_id' => $sku->id,
            'sku_name' => $sku->name,
            'barcode' => $digits,
            'field' => $column,
            'source' => $source,
        ]);

        return $column;
    }
}
