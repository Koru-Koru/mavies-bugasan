<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Business details, verbatim from sql/02_seed.sql. Editable later at
 * Settings -> Business.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_key' => 'business_name',     'setting_value' => 'Mavies Bugasan'],
            ['setting_key' => 'business_address',  'setting_value' => 'Public Market St, Brgy. Poblacion, Marikina City'],
            ['setting_key' => 'business_contact',  'setting_value' => '+63 917 123 4567'],
            ['setting_key' => 'business_facebook', 'setting_value' => 'https://www.facebook.com/profile.php?id=61578261067165'],
            ['setting_key' => 'delivery_note',     'setting_value' => 'Same-day delivery within Quezon City and Marikina for orders placed before 2 PM.'],
        ];

        DB::table('settings')->upsert($settings, ['setting_key'], ['setting_value']);
    }
}
