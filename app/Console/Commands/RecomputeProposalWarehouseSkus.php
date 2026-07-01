<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorCatalogProposal;
use App\Services\DistributorClusterEngine;
use App\Services\DistributorSkuNormalizer;
use Illuminate\Console\Command;

class RecomputeProposalWarehouseSkus extends Command
{
    protected $signature = 'catalog:recompute-proposal-warehouse-skus
                            {--execute : Write the corrected warehouse SKUs (omit for dry-run)}';

    protected $description = 'Re-derive each open proposal\'s warehouse SKU by re-normalizing its evidence raw SKUs with the current normalizer + distributor config, then taking the consensus (the item number the most distributors agree on). Picks up normalizer fixes and new strip rules without a re-crawl. Skips proposals whose warehouse SKU was manually edited.';

    /** @var array<string, array<string, mixed>> distributor id → config */
    private array $configs = [];

    public function handle(DistributorClusterEngine $engine, DistributorSkuNormalizer $normalizer): int
    {
        $dryRun = ! $this->option('execute');

        // Only proposals that haven't yet become a catalog SKU — once promoted, the
        // SKU carries its own warehouse_sku and editing the proposal is moot.
        $proposals = DistributorCatalogProposal::query()
            ->whereNull('resulting_sku_id')
            ->where('status', '!=', DistributorCatalogProposal::STATUS_REJECTED)
            ->get();

        $this->configs = Distributor::whereIn(
            'id',
            $proposals->flatMap(fn (DistributorCatalogProposal $p) => collect($p->evidence ?? [])->pluck('distributor_id'))->filter()->unique(),
        )->get(['id', 'config'])->mapWithKeys(fn (Distributor $d) => [$d->id => $d->config ?? []])->all();

        $changes = [];
        $skippedManual = 0;

        foreach ($proposals as $proposal) {
            $members = $this->renormalizedMembers($proposal, $normalizer);
            $consensus = $engine->consensusWarehouseSku($members, $proposal->upc);

            // Nothing to derive, or the stored value already is the consensus.
            if ($consensus === null || $consensus === $proposal->normalized_sku) {
                continue;
            }

            // A warehouse SKU the admin typed in by hand (it no longer equals the
            // auto-stamped normalized_sku) is left untouched — report it only.
            $manuallyEdited = $proposal->proposed_warehouse_sku !== $proposal->normalized_sku;

            $changes[] = [
                'upc' => $proposal->upc,
                'from' => (string) $proposal->normalized_sku,
                'to' => $consensus,
                'sources' => collect($proposal->evidence ?? [])->pluck('distributor_id')->filter()->unique()->count(),
                'manual' => $manuallyEdited,
            ];

            if ($manuallyEdited) {
                $skippedManual++;

                continue;
            }

            if (! $dryRun) {
                $proposal->forceFill([
                    'normalized_sku' => $consensus,
                    'proposed_warehouse_sku' => $consensus,
                ])->save();
            }
        }

        return $this->report($changes, $skippedManual, $dryRun);
    }

    /**
     * The proposal's evidence members, with a normalized SKU RECOVERED for any
     * member the old normalizer left null (e.g. an alphanumeric code like
     * "56360P2" it used to discard). A member that already has a stored
     * normalized SKU keeps it untouched — the stored value is authoritative and,
     * for some distributors, was derived from a field (mpn) other than raw_sku, so
     * re-normalizing raw_sku would corrupt it. A recovered value that is really a
     * barcode is dropped, never used as a warehouse SKU.
     *
     * @return array<int, array<string, mixed>>
     */
    private function renormalizedMembers(DistributorCatalogProposal $proposal, DistributorSkuNormalizer $normalizer): array
    {
        return collect($proposal->evidence ?? [])->map(function (array $member) use ($normalizer) {
            $stored = $member['normalized_sku'] ?? null;

            if ($stored !== null && $stored !== '') {
                return $member;
            }

            $config = $this->configs[$member['distributor_id'] ?? ''] ?? [];
            $member['normalized_sku'] = $this->recoverItemNumber((string) ($member['raw_sku'] ?? ''), $member['raw_upc'] ?? null, $normalizer, $config);

            return $member;
        })->all();
    }

    /**
     * Normalize a raw SKU to an item number, but reject anything that's really the
     * product's barcode (raw_sku is the EAN for some distributors) — a warehouse
     * SKU is a short item number, never a 11+ digit barcode.
     *
     * @param  array<string, mixed>  $config
     */
    private function recoverItemNumber(string $rawSku, ?string $rawUpc, DistributorSkuNormalizer $normalizer, array $config): ?string
    {
        $value = $normalizer->normalize($rawSku, $config);

        if ($value === null) {
            return null;
        }

        $barcode = $rawUpc !== null ? (preg_replace('/\D/', '', $rawUpc) ?? '') : '';

        if (strlen($value) >= 11 || ($barcode !== '' && $value === $barcode)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, array{upc: string, from: string, to: string, sources: int, manual: bool}>  $changes
     */
    private function report(array $changes, int $skippedManual, bool $dryRun): int
    {
        if ($changes === []) {
            $this->info('All open proposals already carry the consensus warehouse SKU. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->table(
            ['UPC', 'Current', 'Consensus', 'Sources', 'Manual?'],
            collect($changes)->map(fn (array $c) => [
                $c['upc'],
                $c['from'],
                $c['to'],
                $c['sources'],
                $c['manual'] ? 'kept (edited)' : '',
            ])->all(),
        );

        $applied = count($changes) - $skippedManual;

        $this->newLine();
        $this->info(($dryRun ? 'Would correct' : 'Corrected').": {$applied} proposal(s).");

        if ($skippedManual > 0) {
            $this->warn("Left {$skippedManual} manually-edited warehouse SKU(s) untouched — review the rows marked \"kept (edited)\" above.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run — re-run with --execute to apply.');
        }

        return Command::SUCCESS;
    }
}
