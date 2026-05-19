<?php

namespace Database\Seeders;

use App\Models\BalloonSize;
use App\Models\Brand;
use App\Models\Material;
use App\Models\Shape;
use App\Models\Size;
use Illuminate\Database\Seeder;

class BalloonSizeSeeder extends Seeder
{
    public function run(): void
    {
        $latex = Material::where('name', 'Latex')->first();
        $foil = Material::where('name', 'Foil')->first();
        $round = Shape::where('name', 'Round')->first();

        if (! $latex || ! $foil || ! $round) {
            return;
        }

        // All seeded entries are round balloons. Other shapes get added via the
        // admin UI as the catalog grows.
        $shapeId = $round->id;

        $sempertex = Brand::where('name', 'Sempertex')->first();
        $tuftex = Brand::where('name', 'TufTex')->first();
        $qualatex = Brand::where('name', 'Qualatex')->first();

        $small = Size::where('name', '5-inch')->first();
        $medium = Size::where('name', '11-inch')->first();
        $large = Size::where('name', '16-inch')->first();
        $jumbo = Size::where('name', '24-inch')->first();
        $giant = Size::where('name', '36-inch')->first();
        $sModeling = Size::where('name', '160')->first();
        $sModeling260 = Size::where('name', '260')->first();
        $lModeling350 = Size::where('name', '350')->first();
        $lModeling360 = Size::where('name', '360')->first();
        $lModeling660 = Size::where('name', '660')->first();

        $entries = [];

        // Sempertex Latex
        if ($sempertex) {
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $sModeling?->id,        'name' => '160',    'sort_order' => 10];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $sModeling260?->id,     'name' => '260',    'sort_order' => 20];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $lModeling360?->id,     'name' => '360',    'sort_order' => 30];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $small?->id,            'name' => 'R-5',    'sort_order' => 40];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $small?->id,            'name' => 'R-9',    'sort_order' => 50];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $medium?->id,           'name' => 'R-12',   'sort_order' => 60];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $large?->id,            'name' => 'R-18',   'sort_order' => 70];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $jumbo?->id,            'name' => 'R-24',   'sort_order' => 80];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $giant?->id,            'name' => 'R-36',   'sort_order' => 90];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $small?->id,            'name' => 'C-6',    'sort_order' => 100];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $medium?->id,           'name' => 'C-12',   'sort_order' => 110];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $large?->id,            'name' => 'C-14',   'sort_order' => 120];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $small?->id,            'name' => 'LOL-6',  'sort_order' => 130];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $medium?->id,           'name' => 'LOL-12', 'sort_order' => 140];
            $entries[] = ['brand_id' => $sempertex->id, 'material_id' => $latex->id, 'size_id' => $lModeling660?->id,     'name' => 'LOL-660', 'sort_order' => 150];
        }

        // TufTex Latex
        if ($tuftex) {
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $latex->id, 'size_id' => $small?->id,            'name' => '5-inch',  'sort_order' => 200];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $latex->id, 'size_id' => $medium?->id,           'name' => '11-inch', 'sort_order' => 210];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $latex->id, 'size_id' => $large?->id,            'name' => '17-inch', 'sort_order' => 220];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $latex->id, 'size_id' => $jumbo?->id,            'name' => '24-inch', 'sort_order' => 230];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $latex->id, 'size_id' => $giant?->id,            'name' => '36-inch', 'sort_order' => 240];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $latex->id, 'size_id' => $sModeling260?->id,     'name' => '260',     'sort_order' => 250];
        }

        // TufTex Foil
        if ($tuftex && $foil) {
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'size_id' => $large?->id,   'name' => '18-inch', 'sort_order' => 300];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'size_id' => $jumbo?->id,   'name' => '24-inch', 'sort_order' => 310];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'size_id' => $jumbo?->id,   'name' => '26-inch', 'sort_order' => 320];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'size_id' => $jumbo?->id,   'name' => '28-inch', 'sort_order' => 330];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'size_id' => $giant?->id,   'name' => '30-inch', 'sort_order' => 340];
            $entries[] = ['brand_id' => $tuftex->id, 'material_id' => $foil->id, 'size_id' => $giant?->id,   'name' => '34-inch', 'sort_order' => 350];
        }

        // Qualatex Latex (common sizes)
        if ($qualatex) {
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $small?->id,        'name' => '5-inch',  'sort_order' => 400];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $medium?->id,       'name' => '11-inch', 'sort_order' => 410];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $large?->id,        'name' => '16-inch', 'sort_order' => 420];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $jumbo?->id,        'name' => '24-inch', 'sort_order' => 430];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $giant?->id,        'name' => '36-inch', 'sort_order' => 440];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $sModeling?->id,    'name' => '160',     'sort_order' => 450];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $sModeling260?->id, 'name' => '260',     'sort_order' => 460];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $lModeling350?->id, 'name' => '350',     'sort_order' => 470];
            $entries[] = ['brand_id' => $qualatex->id, 'material_id' => $latex->id, 'size_id' => $lModeling660?->id, 'name' => '660',     'sort_order' => 480];
        }

        foreach ($entries as $data) {
            if ($data['size_id'] === null) {
                continue; // skip if size family not found
            }
            $data['shape_id'] = $shapeId;
            BalloonSize::firstOrCreate(
                [
                    'brand_id' => $data['brand_id'],
                    'material_id' => $data['material_id'],
                    'shape_id' => $shapeId,
                    'name' => $data['name'],
                ],
                $data,
            );
        }
    }
}
