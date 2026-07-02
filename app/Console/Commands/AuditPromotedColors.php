<?php

namespace App\Console\Commands;

use App\Models\Color;
use App\Models\DistributorCatalogProposal;
use App\Models\Sku;
use App\Services\CatalogAttributeResolver;
use App\Services\DistributorCatalogPromoter;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class AuditPromotedColors extends Command
{
    protected $signature = 'catalog:audit-promoted-colors
                            {--execute : Correct the affected catalog SKUs (omit for a read-only audit)}';

    protected $description = 'Audit catalog SKUs created from distributor proposals for a colour that disagrees with a fresh re-resolution of the proposal\'s own evidence (the "wrong field silently learned/carried through an edit" bug). Read-only by default; --execute corrects SKUs where the re-resolution is a confident (exact or title-derived) match — anything lower-confidence is reported but never auto-applied.';

    public function handle(DistributorCatalogPromoter $promoter): int
    {
        $dryRun = ! $this->option('execute');

        $proposals = DistributorCatalogProposal::query()
            ->whereNotNull('resulting_sku_id')
            ->get();

        $skus = Sku::whereIn('id', $proposals->pluck('resulting_sku_id')->filter()->unique())
            ->get(['id', 'name', 'color_id'])
            ->keyBy('id');

        $changes = [];
        $lowConfidence = 0;

        foreach ($proposals as $proposal) {
            $sku = $skus->get($proposal->resulting_sku_id);

            if ($sku === null || $sku->color_id === null) {
                continue;
            }

            $recomputed = $promoter->recomputeColorFromEvidence($proposal);

            if ($recomputed === null || $recomputed->id === $sku->color_id) {
                continue;
            }

            // A recomputed value is only trustworthy to auto-apply when it's a
            // clear, specific answer: either the structured field alone resolved it
            // exactly, or the product's own title named an unambiguous shade. A
            // fuzzy structured guess is reported for a human to judge, never applied.
            $confident = $this->isConfident($proposal, $recomputed);

            $changes[] = [
                'sku_id' => $sku->id,
                'name' => $sku->name,
                'from' => Color::find($sku->color_id)?->name ?? $sku->color_id,
                'to' => $recomputed->name,
                'confident' => $confident,
            ];

            if (! $confident) {
                $lowConfidence++;

                continue;
            }

            if (! $dryRun) {
                $sku->forceFill(['color_id' => $recomputed->id])->save();
            }
        }

        return $this->report(collect($changes), $lowConfidence, $dryRun);
    }

    /**
     * Whether the recomputed colour is trustworthy enough to auto-apply: the
     * structured attribute table alone gave an exact match, or the title named
     * this exact shade unambiguously (colorInText only returns a hit when it finds
     * one, so any non-null title match already implies confidence — this just
     * confirms the recomputed colour IS the title's own answer, not a structured
     * fallback that merely differs from the SKU's current value).
     */
    private function isConfident(DistributorCatalogProposal $proposal, Color $recomputed): bool
    {
        $text = collect($proposal->evidence ?? [])
            ->pluck('title')
            ->push($proposal->proposed_name)
            ->filter()
            ->implode(' ');

        return app(CatalogAttributeResolver::class)
            ->colorInText($text, $recomputed->brand)
            ?->is($recomputed) ?? false;
    }

    /**
     * @param  Collection<int, array{sku_id: string, name: ?string, from: string, to: string, confident: bool}>  $changes
     */
    private function report(Collection $changes, int $lowConfidence, bool $dryRun): int
    {
        if ($changes->isEmpty()) {
            $this->info('No promoted SKUs disagree with a fresh re-resolution of their evidence. Nothing to do.');

            return Command::SUCCESS;
        }

        $this->table(
            ['SKU', 'Name', 'Current', 'Re-resolved', 'Confident?'],
            $changes->map(fn (array $c) => [
                mb_strimwidth((string) $c['sku_id'], 0, 8, ''),
                mb_strimwidth((string) $c['name'], 0, 45, '…'),
                $c['from'],
                $c['to'],
                $c['confident'] ? 'yes' : 'LOW — review',
            ])->all(),
        );

        $applied = $changes->where('confident', true)->count();

        $this->newLine();
        $this->info(($dryRun ? 'Would correct' : 'Corrected').": {$applied} confident SKU(s).");

        if ($lowConfidence > 0) {
            $this->warn("{$lowConfidence} additional SKU(s) disagree but only via a fuzzy/structured guess — never auto-applied. Review the rows marked \"LOW — review\" above.");
        }

        if ($dryRun) {
            $this->newLine();
            $this->line('Read-only audit — re-run with --execute to apply the confident corrections.');
        }

        return Command::SUCCESS;
    }
}
