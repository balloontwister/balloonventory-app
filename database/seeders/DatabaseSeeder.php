<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            SuperAdminSeeder::class,
            EmailTemplateSeeder::class,
            // Catalog reference data — order matters: families before children.
            BrandSeeder::class,
            MaterialSeeder::class,
            SizeSeeder::class,
            TextureFamilySeeder::class,
            TextureSeeder::class,
            ColorFamilySeeder::class,
            ShapeSeeder::class,
            ThemeSeeder::class,
            PackagingTypeSeeder::class,
            PrintColorSeeder::class,
            PrintSideSeeder::class,
            PriceCodeSeeder::class,
            BalloonSizeSeeder::class,
            CatalogTranslationSeeder::class,
            QualatexColorSeeder::class,
            TufTexColorSeeder::class,
        ]);
    }
}
