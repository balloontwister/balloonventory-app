<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Color;
use App\Services\ImageAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ImportTufTexClusterImages extends Command
{
    protected $signature = 'tuftex:import-cluster-images
                            {--source= : Path to the "Tuftex Cluster Color Images" folder (defaults to intake/ in the project root)}
                            {--dry-run : Preview matches without storing any files}';

    protected $description = 'Attach cluster images to TufTex colors from the intake folder';

    /**
     * Maps each filename to its DB color name.
     * Filenames with "Crystal"/"Metallic" prefixes or shorthand names are explicit.
     * Five cluster files have no matching DB color and are intentionally omitted:
     *   Crystal Tangerine, Metallic Concord, Metallic Lilac, Metallic Yellow, Sky Blue.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'Aloha.jpg' => 'Aloha',
        'Baby Blue.jpg' => 'Baby Blue',
        'Baby Pink.jpg' => 'Baby Pink',
        'Black.jpg' => 'Black',
        'Blossom.jpg' => 'Blossom',
        'Blue Slate.jpg' => 'Blue Slate',
        'Blue.jpg' => 'Blue',
        'Blush.jpg' => 'Blush',
        'Burgundy.jpg' => 'Burgundy',
        'Burnt Orange.jpg' => 'Burnt Orange',
        'Cameo.jpg' => 'Cameo',
        'Canyon Rose.jpg' => 'Canyon Rose',
        'Cheeky.jpg' => 'Cheeky',
        'Clear Yellow.jpg' => 'Crystal Yellow',
        'Clear.jpg' => 'Clear',
        'Cocoa.jpg' => 'Cocoa',
        'Coral.jpg' => 'Coral',
        'Crystal Emerald.jpg' => 'Emerald Green',
        'Crystal Magenta.jpg' => 'Magenta',
        'Crystal Purple.jpg' => 'Crystal Purple',
        'Crystal Red.jpg' => 'Crystal Red',
        'Crystal Sapphire.jpg' => 'Sapphire Blue',
        'Empowermint.jpg' => 'Empower Mint',
        'Evergreen.jpg' => 'Evergreen',
        'Fiona.jpg' => 'Fiona',
        'Fog.jpg' => 'Fog',
        'Forest Green.jpg' => 'Forest Green',
        'Georgia.jpg' => 'Georgia',
        'Gold.jpg' => 'Gold',
        'Golden effects.JPG' => 'Golden',
        'Goldenrod.jpg' => 'Goldenrod',
        'Gray Smoke.jpg' => 'Gray Smoke',
        'Green.jpg' => 'Green',
        'Hot Pink.jpg' => 'Hot Pink',
        'Lace.jpg' => 'Lace',
        'Lavender.jpg' => 'Lavender',
        'Lemonade.jpg' => 'Lemonade',
        'Lime Green.jpg' => 'Lime Green',
        'Malted.jpg' => 'Malted',
        'Meadow.jpg' => 'Meadow',
        'Metallic Blue.jpg' => 'Metallic Blue',
        'Metallic Fuschia.jpg' => 'Fuchsia',
        'Metallic Green.jpg' => 'Metallic Green',
        'Metallic Midnight.jpg' => 'Midnight Blue',
        'Metallic Starfire.jpg' => 'Starfire Red',
        'Metallic Teal.jpg' => 'Metallic Teal',
        'Monet.jpg' => 'Monet',
        'Muse.jpg' => 'Muse',
        'Mustard.jpg' => 'Mustard',
        'Naval.jpg' => 'Naval',
        'Navy.jpg' => 'Navy',
        'Orange.jpg' => 'Orange',
        'Peri.jpg' => 'Peri',
        'Pink.jpg' => 'Pink',
        'Pixie.jpg' => 'Pixie',
        'Plum Purple.jpg' => 'Plum Purple',
        'Red.jpg' => 'Red',
        'Rockstar effects.jpg' => 'Rockstar Pink',
        'Romey.jpg' => 'Romey',
        'Rose Gold.jpg' => 'Rose Gold',
        'Royalty.jpg' => 'Royalty',
        'Samba.jpg' => 'Samba',
        'Sangria.jpg' => 'Sangria',
        'Scarlett.jpg' => 'Scarlett',
        'Seafoam.jpg' => 'Seafoam',
        'Seaglass.jpg' => 'Sea Glass',
        'Shadow effects.jpg' => 'Shadow',
        'Shimmering Pink.jpg' => 'Shimmering Pink',
        'Silver.jpg' => 'Silver',
        'Silvery effects.JPG' => 'Silvery',
        'Stone.jpg' => 'Stone',
        'Sugar.jpg' => 'Sugar',
        'Taffy.jpg' => 'Taffy',
        'Teal.jpg' => 'Teal',
        'Turquoise.jpg' => 'Turquoise',
        'White.jpg' => 'White',
        'Willow.jpg' => 'Willow',
        'Yellow.jpg' => 'Yellow',
    ];

    public function handle(ImageAttachmentService $images): int
    {
        $source = $this->option('source')
            ?? base_path('intake/Tuftex Cluster Color Images');

        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN — no files will be stored.');
        }

        $ok = 0;
        $failed = 0;

        foreach (self::MAP as $filename => $colorName) {
            $path = $source.'/'.$filename;

            if (! file_exists($path)) {
                $this->warn("  MISSING FILE  {$filename}");
                $failed++;

                continue;
            }

            $color = Color::where('name', $colorName)
                ->where('brand_id', $tuftex->id)
                ->first();

            if (! $color) {
                $this->warn("  COLOR NOT FOUND  {$colorName}");
                $failed++;

                continue;
            }

            if ($dryRun) {
                $this->line("  MATCH  {$colorName}  ←  {$filename}");
                $ok++;

                continue;
            }

            $file = new UploadedFile($path, $filename, mime_content_type($path), null, true);
            $images->set($color, 'cluster', $file);
            $this->line("  OK  {$colorName}");
            $ok++;
        }

        $this->newLine();
        $this->info("Done: {$ok} attached, {$failed} failed.");
        $this->line('Skipped (no DB match): Crystal Tangerine, Metallic Concord, Metallic Lilac, Metallic Yellow, Sky Blue.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
