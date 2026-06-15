<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\Shape;
use App\Models\ShapeTranslation;
use App\Models\Size;
use App\Models\Theme;
use App\Models\ThemeTranslation;
use Illuminate\Database\Seeder;

/**
 * Reference data needed to bring TufTex (and future) foil balloons into the
 * catalog. Unlike the base ShapeSeeder/SizeSeeder/ThemeSeeder — which only
 * plant starter data on a fresh table and skip once production holds curated
 * data — this seeder is additive and safe to run on a populated production
 * database: every write is a keyed firstOrCreate, so existing rows are left
 * untouched and re-runs are no-ops.
 *
 * Adds:
 *  - Foil-scoped shapes (shapes are material-scoped; none existed for Foil).
 *    Per-design die-cuts (ghost, boot, teapot, etc.) all map to the shared
 *    generic "Shaped".
 *  - Generic sizes used by foils that the base SizeSeeder never planted.
 *  - Occasion themes used to classify foils.
 *  - Spanish (es) translations for each new shape and theme.
 */
class FoilCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFoilShapes();
        $this->seedFoilSizes();
        $this->seedThemes();
    }

    /**
     * Shapes are material-scoped. The user's modelling decision: standard
     * Round / Square / Star, plus a single shared generic "Shaped" that all
     * die-cut foil designs (Ghost, Boot, Teapot, Santa Head, Sunglasses,
     * Witch Hat) point at.
     */
    private function seedFoilShapes(): void
    {
        $foil = Material::where('name', 'Foil')->firstOrFail();

        $shapes = [
            ['name' => 'Round',  'sort_order' => 10, 'es' => 'Redondo'],
            ['name' => 'Square', 'sort_order' => 15, 'es' => 'Cuadrado'],
            ['name' => 'Star',   'sort_order' => 60, 'es' => 'Estrella'],
            ['name' => 'Shaped', 'sort_order' => 70, 'es' => 'Con forma'],
        ];

        foreach ($shapes as $data) {
            $shape = Shape::firstOrCreate(
                ['name' => $data['name'], 'material_id' => $foil->id],
                ['sort_order' => $data['sort_order']],
            );

            ShapeTranslation::firstOrCreate(
                ['shape_id' => $shape->id, 'locale' => 'es'],
                ['name' => $data['es']],
            );
        }
    }

    /**
     * Generic size rows (material-agnostic) used by foils. 18", 24" and 36"
     * already exist from SizeSeeder; these six fill the gaps. diameter_cm is
     * the rounded metric value (inches × 2.54). sort_order slots them into the
     * existing round-balloon ordering (18=55, 24=60, 36=70).
     */
    private function seedFoilSizes(): void
    {
        $sizes = [
            ['name' => '22-inch', 'diameter_cm' => 56, 'sort_order' => 57],
            ['name' => '25-inch', 'diameter_cm' => 64, 'sort_order' => 61],
            ['name' => '26-inch', 'diameter_cm' => 66, 'sort_order' => 62],
            ['name' => '28-inch', 'diameter_cm' => 71, 'sort_order' => 63],
            ['name' => '30-inch', 'diameter_cm' => 76, 'sort_order' => 64],
            ['name' => '34-inch', 'diameter_cm' => 86, 'sort_order' => 66],
        ];

        foreach ($sizes as $data) {
            Size::firstOrCreate(['name' => $data['name']], $data);
        }
    }

    /**
     * Occasion themes used to classify foils. Animal, Christmas, Halloween and
     * Stars already exist (base ThemeSeeder) and are reused; these are net-new.
     * sort_order continues past the base themes (which end at 90).
     */
    private function seedThemes(): void
    {
        $themes = [
            ['name' => 'Birthday',        'sort_order' => 100, 'es' => 'Cumpleaños'],
            ['name' => 'Wedding',         'sort_order' => 110, 'es' => 'Boda'],
            ['name' => "Valentine's Day", 'sort_order' => 120, 'es' => 'San Valentín'],
            ['name' => 'Graduation',      'sort_order' => 130, 'es' => 'Graduación'],
            ['name' => 'Communion',       'sort_order' => 140, 'es' => 'Comunión'],
            ['name' => 'Thank You',       'sort_order' => 150, 'es' => 'Gracias'],
            ['name' => 'Patriotic',       'sort_order' => 160, 'es' => 'Patriótico'],
            ['name' => 'Western',         'sort_order' => 170, 'es' => 'Vaquero'],
            ['name' => 'Sports',          'sort_order' => 180, 'es' => 'Deportes'],
            ['name' => 'Aquatic',         'sort_order' => 190, 'es' => 'Acuático'],
            ['name' => 'Everyday',        'sort_order' => 200, 'es' => 'Diario'],
            // Forward-looking occasions for future foils (not used by the
            // initial 45 TufTex designs).
            ['name' => 'Baby Shower',     'sort_order' => 210, 'es' => 'Baby Shower'],
            ['name' => 'Anniversary',     'sort_order' => 220, 'es' => 'Aniversario'],
            ['name' => 'Congratulations', 'sort_order' => 230, 'es' => 'Felicidades'],
            ['name' => 'Retirement',      'sort_order' => 240, 'es' => 'Jubilación'],
            ['name' => 'Get Well',        'sort_order' => 250, 'es' => 'Mejórate pronto'],
            ['name' => 'Baptism',         'sort_order' => 260, 'es' => 'Bautizo'],
            ['name' => 'New Year',        'sort_order' => 270, 'es' => 'Año Nuevo'],
        ];

        foreach ($themes as $data) {
            $theme = Theme::firstOrCreate(
                ['name' => $data['name']],
                ['sort_order' => $data['sort_order']],
            );

            ThemeTranslation::firstOrCreate(
                ['theme_id' => $theme->id, 'locale' => 'es'],
                ['name' => $data['es']],
            );
        }
    }
}
