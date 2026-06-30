<?php

namespace App\Services\Distributors;

use App\Models\DistributorLearnedAlias;

/**
 * Read/write gateway for {@see DistributorLearnedAlias}. Centralises key
 * normalization so the value the matcher looks up and the value the review
 * service records always hash the same way, and caches the whole (small) table in
 * memory so the matcher can consult it for every proposal on a queue page without
 * a query each time.
 *
 * Registered as a singleton, so a {@see record()} during an edit invalidates the
 * cache for the re-stamp pass that follows in the same request.
 */
class DistributorLearnedAliasStore
{
    /** @var array<string, string>|null  composite key → catalog_id */
    private ?array $cache = null;

    /**
     * The catalog reference id an admin previously mapped this raw distributor
     * value to, or null if nothing's been learned for this scope yet. `$brandId`
     * is null for the brand and packaging attributes (no brand scope).
     */
    public function lookup(string $distributorId, string $attribute, ?string $brandId, string $rawValue): ?string
    {
        return $this->all()[$this->key($distributorId, $attribute, $brandId, $rawValue)] ?? null;
    }

    /**
     * Upsert a learned alias for a scope. Idempotent on the
     * (distributor, attribute, brand, raw value) key, so re-confirming a mapping
     * just refreshes its catalog target / note.
     */
    public function record(
        string $distributorId,
        string $attribute,
        ?string $brandId,
        string $rawValue,
        string $catalogId,
        ?string $note,
        ?string $userId,
    ): DistributorLearnedAlias {
        $alias = DistributorLearnedAlias::updateOrCreate(
            [
                'distributor_id' => $distributorId,
                'attribute' => $attribute,
                'brand_id' => $brandId ?? '',
                'raw_value_normalized' => $this->normalize($rawValue),
            ],
            [
                'catalog_id' => $catalogId,
                'note' => $note,
                'created_by' => $userId,
            ],
        );

        $this->cache = null;

        return $alias;
    }

    public static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        return $this->cache ??= DistributorLearnedAlias::all()
            ->mapWithKeys(fn (DistributorLearnedAlias $a) => [
                $this->key($a->distributor_id, $a->attribute, $a->brand_id, $a->raw_value_normalized) => $a->catalog_id,
            ])
            ->all();
    }

    private function key(string $distributorId, string $attribute, ?string $brandId, string $rawValue): string
    {
        // Empty brand scope and the unscoped (null) case hash identically, so a
        // brand/packaging alias (stored with brand_id '') is found whether the
        // caller passes null or ''.
        return implode('|', [
            $distributorId,
            $attribute,
            $brandId === null || $brandId === '' ? '' : $brandId,
            self::normalize($rawValue),
        ]);
    }
}
