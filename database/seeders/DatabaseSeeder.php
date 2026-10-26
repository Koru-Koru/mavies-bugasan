<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database in the same order as sql/02_seed.sql:
     * varieties first, then settings. No admin account is ever seeded here —
     * the owner is created once via `php artisan mavies:make-owner`.
     */
    public function run(): void
    {
        $this->call([
            InventorySeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
