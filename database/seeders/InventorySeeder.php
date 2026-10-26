<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The 15 real varieties, verbatim from sql/02_seed.sql — do not add, remove,
 * reorder, or invent weights/prices. Every variety starts at stock 0 so the
 * stock_movements ledger is the single source of truth from day one.
 *
 * Mirrors the dump's ON DUPLICATE KEY UPDATE: re-seeding refreshes category
 * and prices but never resets stock (stock is only ever moved through the
 * admin Stock screen, which writes a movement row).
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $varieties = [
            ['Coco Pandan',           'Imported',    0.00,    0.00],
            ['Sweet Hasmin',          'Imported',    0.00,    0.00],
            ['SP1 Dinorado',          'Imported', 1380.00, 1350.00],
            ['Ube Pandan',            'Imported', 1330.00, 1330.00],
            ['Coco Japonica',         'Japanese', 1350.00, 1320.00],
            ['Saporro Japanese Rice', 'Japanese', 1350.00, 1320.00],
            ['Sakura Japanese Rice',  'Japanese', 1350.00, 1320.00],
            ['Coco Thai (Alsa)',      'Thailand',    0.00,    0.00],
            ['Royal Sheep (Alsa)',    'Thailand', 1185.00, 1155.00],
            ['Magic Castle (Alsa)',   'Thailand', 1220.00, 1190.00],
            ['Planters (Alsa)',       'Thailand', 1185.00, 1155.00],
            ['MCL Denorado',          'Local',    1380.00, 1330.00],
            ['Boy Kanin',             'Local',    1180.00, 1180.00],
            ['Buko Pandan',           'Local',       0.00,    0.00],
            ['SP1 Angelica',          'Local',       0.00,    0.00],
        ];

        foreach ($varieties as [$name, $category, $unit, $wholesale]) {
            DB::table('inventory')->insertOrIgnore([
                'name'                => $name,
                'category'            => $category,
                'unit_price'          => $unit,
                'wholesale_price'     => $wholesale,
                'stock'               => 0,
                'low_stock_threshold' => 10,
            ]);

            DB::table('inventory')->where('name', $name)->update([
                'category'        => $category,
                'unit_price'      => $unit,
                'wholesale_price' => $wholesale,
            ]);
        }
    }
}
