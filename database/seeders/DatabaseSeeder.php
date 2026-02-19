<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // Core lookup tables (service categories, VAT, product categories, etc)
            ServiceLookupsSeeder::class,

            // Permissions used by menus/routes/guards
            PermissionsSeeder::class,
        ]);
    }
}
