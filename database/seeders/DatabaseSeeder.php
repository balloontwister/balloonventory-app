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
            // Catalog reference data — order matters: color families before colors
            BrandSeeder::class,
            SizeSeeder::class,
            ShapeSeeder::class,
            TextureSeeder::class,
            ColorFamilySeeder::class,
            ThemeSeeder::class,
            MaterialSeeder::class,
            CatalogTranslationSeeder::class,
        ]);
    }
}
