<?php

namespace App\Services\Distributors;

/**
 * The structured outcome of attempting to promote a catalog proposal into a
 * real Sku from the review queue. The controller maps this to a flash message
 * and the UI uses {@see $missingAttributes} to tell the admin exactly what to
 * map in the Edit modal.
 *
 * Status values:
 * - `created`          — a Sku was created (id in {@see $skuId}).
 * - `already_promoted` — the proposal already owns a Sku ({@see $skuId}); approval
 *                        just records the human decision.
 * - `needs_mapping`    — approved, but brand/size/colour can't all be resolved, so
 *                        no Sku was created. {@see $missingAttributes} lists which.
 * - `upc_conflict`     — the proposal's UPC already belongs to a catalog Sku; we
 *                        will not create a duplicate.
 */
final class ProposalPromotionResult
{
    public const STATUS_CREATED = 'created';

    public const STATUS_ALREADY_PROMOTED = 'already_promoted';

    public const STATUS_NEEDS_MAPPING = 'needs_mapping';

    public const STATUS_UPC_CONFLICT = 'upc_conflict';

    /**
     * @param  array<int, string>  $missingAttributes  Subset of ['brand', 'balloon_size', 'color'].
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $skuId = null,
        public readonly array $missingAttributes = [],
    ) {}

    public static function created(string $skuId): self
    {
        return new self(self::STATUS_CREATED, $skuId);
    }

    public static function alreadyPromoted(string $skuId): self
    {
        return new self(self::STATUS_ALREADY_PROMOTED, $skuId);
    }

    /**
     * @param  array<int, string>  $missingAttributes
     */
    public static function needsMapping(array $missingAttributes): self
    {
        return new self(self::STATUS_NEEDS_MAPPING, null, $missingAttributes);
    }

    public static function upcConflict(): self
    {
        return new self(self::STATUS_UPC_CONFLICT);
    }

    public function skuWasCreated(): bool
    {
        return $this->status === self::STATUS_CREATED;
    }
}
