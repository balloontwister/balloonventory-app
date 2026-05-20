<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Color;
use App\Services\ImageAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;

class ImportTufTexColorImages extends Command
{
    protected $signature = 'tuftex:import-images
                            {--source= : Path to the "Tuftex Single Color Images" folder (defaults to intake/ in the project root)}
                            {--dry-run : Preview matches without storing any files}';

    protected $description = 'Attach single-balloon images to TufTex colors from the intake folder';

    /**
     * Maps each image path (relative to the source folder) to its DB color name.
     * Crystal and Effects filenames don't match DB names, so they are explicit.
     * Pearl colors were placed in the Metallic-Oval folder by the supplier.
     *
     * @var array<string, string>
     */
    private const MAP = [
        // ── Designer ──────────────────────────────────────────────────────────
        'Designer-Oval/aloha.png' => 'Aloha',
        'Designer-Oval/baby blue.png' => 'Baby Blue',
        'Designer-Oval/baby pink.png' => 'Baby Pink',
        'Designer-Oval/black.png' => 'Black',
        'Designer-Oval/blossom.png' => 'Blossom',
        'Designer-Oval/blue slate.png' => 'Blue Slate',
        'Designer-Oval/blush.png' => 'Blush',
        'Designer-Oval/burnt orange.png' => 'Burnt Orange',
        'Designer-Oval/cameo.png' => 'Cameo',
        'Designer-Oval/canyon rose.png' => 'Canyon Rose',
        'Designer-Oval/cheeky.png' => 'Cheeky',
        'Designer-Oval/cocoa.png' => 'Cocoa',
        'Designer-Oval/coral.png' => 'Coral',
        'Designer-Oval/empower-mint.png' => 'Empower Mint',
        'Designer-Oval/evergreen.png' => 'Evergreen',
        'Designer-Oval/fiona.png' => 'Fiona',
        'Designer-Oval/fog.png' => 'Fog',
        'Designer-Oval/goldenrod.png' => 'Goldenrod',
        'Designer-Oval/gray smoke.png' => 'Gray Smoke',
        'Designer-Oval/hot pink.png' => 'Hot Pink',
        'Designer-Oval/lace.png' => 'Lace',
        'Designer-Oval/lavender.png' => 'Lavender',
        'Designer-Oval/lemonade.png' => 'Lemonade',
        'Designer-Oval/lime green.png' => 'Lime Green',
        'Designer-Oval/malted.png' => 'Malted',
        'Designer-Oval/monet.png' => 'Monet',
        'Designer-Oval/muse.png' => 'Muse',
        'Designer-Oval/mustard.png' => 'Mustard',
        'Designer-Oval/naval.png' => 'Naval',
        'Designer-Oval/navy.png' => 'Navy',
        'Designer-Oval/peri.png' => 'Peri',
        'Designer-Oval/pixie.png' => 'Pixie',
        'Designer-Oval/plum purple.png' => 'Plum Purple',
        'Designer-Oval/royalty.png' => 'Royalty',
        'Designer-Oval/samba.png' => 'Samba',
        'Designer-Oval/sangria.png' => 'Sangria',
        'Designer-Oval/scarlett.png' => 'Scarlett',
        'Designer-Oval/sea glass.png' => 'Sea Glass',
        'Designer-Oval/stone.png' => 'Stone',
        'Designer-Oval/taffy.png' => 'Taffy',
        'Designer-Oval/teal.png' => 'Teal',
        'Designer-Oval/turquoise.png' => 'Turquoise',
        'Designer-Oval/willow.png' => 'Willow',

        // ── Standard ──────────────────────────────────────────────────────────
        'Standard-Oval/blue.png' => 'Blue',
        'Standard-Oval/green.png' => 'Green',
        'Standard-Oval/orange.png' => 'Orange',
        'Standard-Oval/pink.png' => 'Pink',
        'Standard-Oval/red.png' => 'Red',
        'Standard-Oval/white.png' => 'White',
        'Standard-Oval/yellow.png' => 'Yellow',

        // ── Crystal ───────────────────────────────────────────────────────────
        // Filenames don't match DB names — explicit mapping required.
        'Crystal-Oval/burgundy.png' => 'Burgundy',
        'Crystal-Oval/clear yellow.png' => 'Crystal Yellow',
        'Crystal-Oval/clear.png' => 'Clear',
        'Crystal-Oval/crystal red.png' => 'Crystal Red',
        'Crystal-Oval/emerald green.png' => 'Emerald Green',
        'Crystal-Oval/magenta.png' => 'Magenta',
        'Crystal-Oval/purple.png' => 'Crystal Purple',
        'Crystal-Oval/sapphire blue.png' => 'Sapphire Blue',

        // ── Metallic ──────────────────────────────────────────────────────────
        'Metallic-Oval/forest green.png' => 'Forest Green',
        'Metallic-Oval/gold.png' => 'Gold',
        'Metallic-Oval/metallic blue.png' => 'Metallic Blue',
        'Metallic-Oval/metallic green.png' => 'Metallic Green',
        'Metallic-Oval/metallic teal.png' => 'Metallic Teal',
        'Metallic-Oval/midnight blue.png' => 'Midnight Blue',
        'Metallic-Oval/rose gold.png' => 'Rose Gold',
        'Metallic-Oval/seafoam.png' => 'Seafoam',
        'Metallic-Oval/silver.png' => 'Silver',
        'Metallic-Oval/starfire red.png' => 'Starfire Red',

        // ── Pearl (supplier placed these in Metallic-Oval) ────────────────────
        // "Pearl Lace" has no image in the intake folder — intentionally omitted.
        'Metallic-Oval/fuchsia.png' => 'Fuchsia',
        'Metallic-Oval/georgia.png' => 'Georgia',
        'Metallic-Oval/meadow.png' => 'Meadow',
        'Metallic-Oval/romey.png' => 'Romey',
        'Metallic-Oval/shimmering pink.png' => 'Shimmering Pink',
        'Metallic-Oval/sugar.png' => 'Sugar',

        // ── Effects ───────────────────────────────────────────────────────────
        // Filenames include PMS codes — explicit mapping required.
        'Effects-Oval/Golden-871C-Oval.png' => 'Golden',
        'Effects-Oval/Rockstar-Pink-7433C-Oval.png' => 'Rockstar Pink',
        'Effects-Oval/Shadow-426C-Oval.png' => 'Shadow',
        'Effects-Oval/Silvery-877C-Oval.png' => 'Silvery',
    ];

    public function handle(ImageAttachmentService $images): int
    {
        $source = $this->option('source')
            ?? base_path('intake/Tuftex Single Color Images');

        $tuftex = Brand::where('name', 'TufTex')->firstOrFail();
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY-RUN — no files will be stored.');
        }

        $ok = 0;
        $failed = 0;

        foreach (self::MAP as $relative => $colorName) {
            $path = $source.'/'.$relative;

            if (! file_exists($path)) {
                $this->warn("  MISSING FILE  {$relative}");
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
                $this->line("  MATCH  {$colorName}  ←  {$relative}");
                $ok++;

                continue;
            }

            $file = new UploadedFile($path, basename($path), mime_content_type($path), null, true);
            $images->set($color, 'single', $file);
            $this->line("  OK  {$colorName}");
            $ok++;
        }

        $this->newLine();
        $this->info("Done: {$ok} attached, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
