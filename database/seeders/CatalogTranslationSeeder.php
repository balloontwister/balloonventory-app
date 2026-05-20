<?php

namespace Database\Seeders;

use App\Models\ColorFamily;
use App\Models\ColorFamilyTranslation;
use App\Models\Material;
use App\Models\MaterialTranslation;
use App\Models\Shape;
use App\Models\ShapeTranslation;
use App\Models\Texture;
use App\Models\TextureFamily;
use App\Models\TextureFamilyTranslation;
use App\Models\TextureTranslation;
use App\Models\Theme;
use App\Models\ThemeTranslation;
use Illuminate\Database\Seeder;

class CatalogTranslationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedShapes();
        $this->seedMaterials();
        $this->seedTextures();
        $this->seedTextureFamilies();
        $this->seedColorFamilies();
        $this->seedThemes();
    }

    private function seedShapes(): void
    {
        $map = [
            'Round' => 'Redondo',
            'Link' => 'Eslabón',
            'Non-round' => 'No redondo',
            'Heart' => 'Corazón',
            'Circle' => 'Círculo',
            'Star' => 'Estrella',
            'Shaped' => 'Con forma',
            'SuperShape' => 'Súper forma',
            'Other' => 'Otro',
        ];

        foreach ($map as $en => $es) {
            $shape = Shape::where('name', $en)->first();
            if ($shape) {
                ShapeTranslation::updateOrCreate(
                    ['shape_id' => $shape->id, 'locale' => 'es'],
                    ['name' => $es],
                );
            }
        }
    }

    private function seedMaterials(): void
    {
        $map = [
            'Latex' => 'Látex',
            'Foil' => 'Foil',
            'Plastic' => 'Plástico',
            'Chloroprene' => 'Cloropreno',
            'Stretchy' => 'Elástico',
        ];

        foreach ($map as $en => $es) {
            $material = Material::where('name', $en)->first();
            if ($material) {
                MaterialTranslation::updateOrCreate(
                    ['material_id' => $material->id, 'locale' => 'es'],
                    ['name' => $es],
                );
            }
        }
    }

    private function seedTextures(): void
    {
        $map = [
            'Crystal' => 'Cristal',
            'Standard' => 'Estándar',
            'Matte' => 'Mate',
            'Glow-in-the-dark' => 'Brilla en la oscuridad',
            'Metallic' => 'Metálico',
            'Pearl' => 'Perla',
            'Neon' => 'Neón',
            'Chrome' => 'Cromo',
            'Satin' => 'Satín',
        ];

        foreach ($map as $en => $es) {
            $texture = Texture::where('name', $en)->first();
            if ($texture) {
                TextureTranslation::updateOrCreate(
                    ['texture_id' => $texture->id, 'locale' => 'es'],
                    ['name' => $es],
                );
            }
        }
    }

    private function seedTextureFamilies(): void
    {
        $map = [
            'Standard' => [
                'name' => 'Estándar',
                'description' => 'Los globos estándar tienen colores sólidos y vibrantes.',
            ],
            'Crystal' => [
                'name' => 'Cristal',
                'description' => 'Los globos de cristal tienen una apariencia transparente, como de vidrio.',
            ],
            'Pastel' => [
                'name' => 'Pastel',
                'description' => 'Los pasteles tienen una luminosidad relativamente alta y una saturación baja a moderada, lo que les da una apariencia suave y apagada.',
            ],
            'Dusk' => [
                'name' => 'Crepúsculo',
                'description' => 'Los crepúsculos tienen un acabado mate oscuro, tonos suaves mezclados con grises sutiles.',
            ],
            'Silk' => [
                'name' => 'Seda',
                'description' => 'Las sedas tienen una superficie semibillante sutil, entre mate y brillante.',
            ],
            'Metallic' => [
                'name' => 'Metálico',
                'description' => 'Los globos metálicos tienen un brillo perlado o brillante que refleja la luz suavemente.',
            ],
            'Neon' => [
                'name' => 'Neón',
                'description' => 'Los neones son colores brillantes y fluorescentes que parecen brillar, especialmente bajo luz ultravioleta.',
            ],
            'Chrome' => [
                'name' => 'Cromo',
                'description' => 'Los globos de cromo son una versión más intensa y altamente reflectante de los globos metálicos.',
            ],
        ];

        foreach ($map as $en => $translations) {
            $family = TextureFamily::where('name', $en)->first();
            if ($family) {
                TextureFamilyTranslation::updateOrCreate(
                    ['texture_family_id' => $family->id, 'locale' => 'es'],
                    ['name' => $translations['name'], 'description' => $translations['description']],
                );
            }
        }
    }

    private function seedColorFamilies(): void
    {
        $map = [
            'Reds' => 'Rojos',
            'Pinks' => 'Rosas',
            'Oranges' => 'Naranjas',
            'Yellows' => 'Amarillos',
            'Greens' => 'Verdes',
            'Blues' => 'Azules',
            'Purples' => 'Púrpuras',
            'Browns' => 'Marrones',
            'Whites' => 'Blancos',
            'Blacks' => 'Negros',
            'Silvers' => 'Plateados',
            'Golds' => 'Dorados',
            'Clears' => 'Transparentes',
        ];

        foreach ($map as $en => $es) {
            $family = ColorFamily::where('name', $en)->first();
            if ($family) {
                ColorFamilyTranslation::updateOrCreate(
                    ['color_family_id' => $family->id, 'locale' => 'es'],
                    ['name' => $es],
                );
            }
        }
    }

    private function seedThemes(): void
    {
        // Proper nouns (Star Wars) stay in English per the roadmap.
        $map = [
            'Holiday' => 'Festivo',
            'Christmas' => 'Navidad',
            'Halloween' => 'Halloween',
            'Stars' => 'Estrellas',
            'Animal' => 'Animal',
            // Star Wars — proper noun, not translated
            'Princess' => 'Princesa',
            'Cartoon' => 'Caricatura',
            'Jungle' => 'Jungla',
        ];

        foreach ($map as $en => $es) {
            $theme = Theme::where('name', $en)->first();
            if ($theme) {
                ThemeTranslation::updateOrCreate(
                    ['theme_id' => $theme->id, 'locale' => 'es'],
                    ['name' => $es],
                );
            }
        }
    }
}
