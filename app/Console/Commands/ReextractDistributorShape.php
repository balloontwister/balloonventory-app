<?php

namespace App\Console\Commands;

use App\Models\Distributor;
use App\Models\DistributorProduct;
use App\Services\Distributors\TitleAttributeExtractor;
use Illuminate\Console\Command;

class ReextractDistributorShape extends Command
{
    protected $signature = 'catalog:reextract-distributor-shape
                            {slug : Distributor slug}
                            {--execute : Write the corrected shape attribute (omit for dry-run)}';

    protected $description = 'Re-derive Balloon Type / Shape for a title-extraction distributor\'s already-staged products from their stored title, using the CURRENT shape_keywords config — without a re-crawl. Run after fixing a missing/wrong shape_keywords entry (e.g. a bare "Link" spelling that used to fall through to the Round default). Only correct for a distributor using extraction.title_attributes (TitleAttributeExtractor); a table/tag-extraction distributor reads shape from the page directly and has nothing to re-derive here.';

    public function handle(TitleAttributeExtractor $extractor): int
    {
        $dryRun = ! $this->option('execute');
        $slug = $this->argument('slug');

        $distributor = Distributor::where('slug', $slug)->first();

        if ($distributor === null) {
            $this->error("No distributor found with slug '{$slug}'.");

            return Command::FAILURE;
        }

        $recipe = $distributor->config['extraction']['title_attributes'] ?? null;

        if (! is_array($recipe)) {
            $this->error("{$distributor->name} has no extraction.title_attributes config — nothing to re-derive.");

            return Command::FAILURE;
        }

        $changed = 0;
        $samples = [];

        DistributorProduct::where('distributor_id', $distributor->id)
            ->chunkById(500, function ($products) use ($extractor, $recipe, $dryRun, &$changed, &$samples) {
                foreach ($products as $product) {
                    $current = $product->raw_data['attributes']['Balloon Type / Shape'][0] ?? null;

                    // Only latex products carry a shape at all (see extract()) —
                    // nothing to re-derive for foil/plastic/unclassified rows.
                    if ($current === null) {
                        continue;
                    }

                    $fresh = $extractor->shape((string) $product->title, $recipe);

                    if ($fresh === $current) {
                        continue;
                    }

                    $changed++;

                    if (count($samples) < 20) {
                        $samples[] = [$product->title, $current, $fresh];
                    }

                    if (! $dryRun) {
                        $rawData = $product->raw_data;
                        $rawData['attributes']['Balloon Type / Shape'] = [$fresh];
                        $product->forceFill(['raw_data' => $rawData])->save();
                    }
                }
            });

        if ($changed === 0) {
            $this->info("All {$distributor->name} staged products already match the current shape config. Nothing to do.");

            return Command::SUCCESS;
        }

        $this->table(['Title', 'Was', 'Now'], $samples);

        if ($changed > count($samples)) {
            $this->line('… and '.($changed - count($samples)).' more.');
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would update' : 'Updated').": {$changed} staged product(s).");

        if ($dryRun) {
            $this->newLine();
            $this->line('Dry run — re-run with --execute to apply, then re-cluster (catalog:cluster-distributors --execute) so open proposals pick up the correction.');
        }

        return Command::SUCCESS;
    }
}
